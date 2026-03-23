-- Remove denormalized counter columns; counts are computed live from likes/boosts/posts tables
ALTER TABLE posts DROP COLUMN replies_count;
ALTER TABLE posts DROP COLUMN likes_count;
ALTER TABLE posts DROP COLUMN boosts_count;
ALTER TABLE posts DROP COLUMN quotes_count;
