-- Clean up SKU codes from existing Saree and Beauty products
USE trackit;

-- Remove SKU codes from Saree products
UPDATE products SET sku = NULL WHERE category = 'Saree' AND sku IS NOT NULL;

-- Remove SKU codes from Beauty products  
UPDATE products SET sku = NULL WHERE category = 'Beauty' AND sku IS NOT NULL;

-- Also clear any product_code that matches the old SKU pattern
UPDATE products SET product_code = NULL WHERE category IN ('Saree', 'Beauty') AND product_code IS NOT NULL;

-- Remove SKU codes from all other products too (optional - uncomment if you want)
-- UPDATE products SET sku = NULL WHERE sku IS NOT NULL;
-- UPDATE products SET product_code = NULL WHERE product_code IS NOT NULL;

SELECT 'SKU codes have been removed from Saree and Beauty products' AS status;
