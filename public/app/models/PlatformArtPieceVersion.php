<?php

declare(strict_types=1);

class PlatformArtPieceVersion
{
    private const SELECT_COLUMNS = "v.id, v.art_piece_id, v.version_number, v.prompt, v.structured_spec,
                    v.html_code, v.css_code, v.generated_code, v.engine,
                    v.generation_vendor, v.generation_model, v.validation_status,
                    v.generation_attempt_count, v.notes, v.created_at,
                    v.ai_profile_id, v.ai_persona_id,
                    v.is_draft_attempt, v.attempt_sequence_token,
                    uavs.profile_name AS ai_profile_name,
                    ap.name AS ai_persona_name";

    private const SELECT_JOINS = "LEFT JOIN user_ai_vendor_settings uavs ON uavs.id = v.ai_profile_id
             LEFT JOIN ai_personas ap ON ap.id = v.ai_persona_id";

    // Excludes is_draft_attempt rows by default — these are AI Refine
    // scratch attempts (see the 2026-06-21 migration), not real version
    // history, and must never appear to a non-admin audience (public piece
    // pages, the API, the immersive viewer). The admin Versions list is the
    // one place that intentionally wants to see them — use
    // allForPieceIncludingDrafts() there instead.
    public static function allForPiece(int $pieceId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT " . self::SELECT_COLUMNS . "
             FROM art_piece_versions v
             " . self::SELECT_JOINS . "
             WHERE v.art_piece_id = ? AND v.is_draft_attempt = 0
             ORDER BY v.version_number DESC, v.created_at DESC"
        );
        $stmt->execute([$pieceId]);
        return $stmt->fetchAll();
    }

    public static function allForPieceIncludingDrafts(int $pieceId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT " . self::SELECT_COLUMNS . "
             FROM art_piece_versions v
             " . self::SELECT_JOINS . "
             WHERE v.art_piece_id = ?
             ORDER BY v.version_number DESC, v.created_at DESC"
        );
        $stmt->execute([$pieceId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            "SELECT " . self::SELECT_COLUMNS . "
             FROM art_piece_versions v
             " . self::SELECT_JOINS . "
             WHERE v.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO art_piece_versions
                (art_piece_id, version_number, prompt, structured_spec,
                 html_code, css_code, generated_code, engine,
                 generation_vendor, generation_model, validation_status,
                 generation_attempt_count, notes, ai_profile_id, ai_persona_id,
                 is_draft_attempt, attempt_sequence_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['art_piece_id'],
            $data['version_number'] ?? 1,
            $data['prompt'] ?? null,
            $data['structured_spec'] ?? null,
            $data['html_code'] ?? null,
            $data['css_code'] ?? null,
            $data['generated_code'] ?? null,
            $data['engine'] ?? 'p5',
            $data['generation_vendor'] ?? null,
            $data['generation_model'] ?? null,
            $data['validation_status'] ?? 'validated',
            $data['generation_attempt_count'] ?? 1,
            $data['notes'] ?? null,
            isset($data['ai_profile_id']) ? ($data['ai_profile_id'] ?: null) : null,
            isset($data['ai_persona_id']) ? ($data['ai_persona_id'] ?: null) : null,
            isset($data['is_draft_attempt']) ? (int)(bool) $data['is_draft_attempt'] : 0,
            $data['attempt_sequence_token'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE art_piece_versions SET
                prompt = ?, structured_spec = ?, html_code = ?, css_code = ?,
                generated_code = ?, engine = ?, generation_vendor = ?,
                generation_model = ?, validation_status = ?,
                generation_attempt_count = ?, notes = ?,
                ai_profile_id = ?, ai_persona_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['prompt'] ?? null,
            $data['structured_spec'] ?? null,
            $data['html_code'] ?? null,
            $data['css_code'] ?? null,
            $data['generated_code'] ?? null,
            $data['engine'] ?? 'p5',
            $data['generation_vendor'] ?? null,
            $data['generation_model'] ?? null,
            $data['validation_status'] ?? 'validated',
            $data['generation_attempt_count'] ?? 1,
            $data['notes'] ?? null,
            isset($data['ai_profile_id']) ? ($data['ai_profile_id'] ?: null) : null,
            isset($data['ai_persona_id']) ? ($data['ai_persona_id'] ?: null) : null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM art_piece_versions WHERE id = ?');
        $stmt->execute([$id]);
    }

    // Flips a draft-attempt row into a real, current-eligible version when
    // the user accepts a successful AI Refine attempt — deliberately
    // narrow (just is_draft_attempt + prompt) rather than reusing the
    // generic update(), so the admin's hand-edit form's behavior can never
    // be affected by this internal bookkeeping field.
    public static function promoteDraftToCurrent(int $id, ?string $prompt): void
    {
        $stmt = db()->prepare(
            'UPDATE art_piece_versions SET is_draft_attempt = 0, prompt = ? WHERE id = ?'
        );
        $stmt->execute([$prompt, $id]);
    }

    // Deletes every draft-attempt row sharing $sequenceToken except
    // $exceptId (the attempt being promoted to current) — the "delete
    // failed siblings once one succeeds" cleanup. Only ever targets rows
    // still flagged is_draft_attempt = 1, so an already-promoted version
    // can never be accidentally swept up.
    public static function deleteBySequenceToken(int $pieceId, string $sequenceToken, int $exceptId): void
    {
        $stmt = db()->prepare(
            'DELETE FROM art_piece_versions
             WHERE art_piece_id = ? AND attempt_sequence_token = ? AND is_draft_attempt = 1 AND id != ?'
        );
        $stmt->execute([$pieceId, $sequenceToken, $exceptId]);
    }

    public static function nextVersionNumber(int $pieceId): int
    {
        $stmt = db()->prepare(
            'SELECT MAX(version_number) FROM art_piece_versions WHERE art_piece_id = ?'
        );
        $stmt->execute([$pieceId]);
        $max = (int) $stmt->fetchColumn();
        return $max + 1;
    }

    private static function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['art_piece_versions']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
