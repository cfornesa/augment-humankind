CREATE TABLE artwork_categories (
    artwork_id  INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (artwork_id, category_id),
    FOREIGN KEY (artwork_id)  REFERENCES artworks(id)   ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

INSERT INTO artwork_categories (artwork_id, category_id)
SELECT id, category_id FROM artworks WHERE category_id IS NOT NULL;
