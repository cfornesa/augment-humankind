-- Migration: 2026-07-08 add 'model' to exhibit_media_items.media_kind
-- Lets 3D model media (OBJ/GLTF/GLB, stored in media_files under a canonical
-- model/* MIME) be placed into exhibits via the media picker. The AI
-- auto-wire path for Three.js/A-Frame pieces uses /media/{id} refs directly
-- and does NOT depend on this ENUM value.
-- Idempotent in setup-database.php via ensureEnumValue(); this record shows the
-- resulting column definition.

ALTER TABLE exhibit_media_items
    MODIFY media_kind ENUM('image', 'video', 'iframe', 'content', 'model') NOT NULL;
