-- Restore automatic product codes for existing products
USE trackit;

-- Generate product codes for products that don't have them
-- This will add the category-based codes like SR123456, BP123456, etc.

-- Update Saree products with SR codes
UPDATE products 
SET product_code = CONCAT('SR', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category = 'Saree' AND (product_code IS NULL OR product_code = '');

-- Update Beauty products with BP codes  
UPDATE products 
SET product_code = CONCAT('BP', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category = 'Beauty' AND (product_code IS NULL OR product_code = '');

-- Update Accessories products with AC codes
UPDATE products 
SET product_code = CONCAT('AC', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category = 'Accessories' AND (product_code IS NULL OR product_code = '');

-- Update Electronics products with EC codes
UPDATE products 
SET product_code = CONCAT('EC', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category = 'Electronics' AND (product_code IS NULL OR product_code = '');

-- Update other categories
UPDATE products 
SET product_code = CONCAT('CL', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category = 'Clothing' AND (product_code IS NULL OR product_code = '');

UPDATE products 
SET product_code = CONCAT('OT', LPAD(FLOOR(RAND() * 1000000), 6, '0'))
WHERE category NOT IN ('Saree', 'Beauty', 'Accessories', 'Electronics', 'Clothing') 
AND (product_code IS NULL OR product_code = '');

SELECT 'Product codes have been restored with category-based prefixes' AS status;
