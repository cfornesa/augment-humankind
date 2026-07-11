-- Cleanup for sonic_params rows fabricated by a since-fixed admin-save bug:
-- resolveSonicParamsFromPost() briefly materialized a {enabled, extras} block
-- for pieces that never had an AI sound design, making sound-less pieces
-- render the full sound panel and play idle notes. Real AI sound designs
-- always carry tempo/scale/instrument (see art_piece_sonic_params_from_feel),
-- so rows missing all three are fabricated and safe to NULL.
UPDATE art_piece_versions
SET sonic_params = NULL
WHERE sonic_params IS NOT NULL
  AND JSON_VALID(sonic_params)
  AND JSON_EXTRACT(sonic_params, '$.tempo') IS NULL
  AND JSON_EXTRACT(sonic_params, '$.scale') IS NULL
  AND JSON_EXTRACT(sonic_params, '$.instrument') IS NULL;
