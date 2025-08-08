-- Clean up existing SKU codes but keep the automatically generated product codes
USE trackit;

-- Clear SKU values to remove the manual codes showing in the interface
-- This will remove "SKU: BSS001" type displays but keep the automatic product codes
UPDATE products SET sku = NULL WHERE sku IS NOT NULL;

-- DO NOT touch product_code - keep the automatically generated codes like:
-- SR123456 (Saree), BP123456 (Beauty), AC123456 (Accessories), EC123456 (Electronics)

SELECT 'SKU codes removed, automatic product codes preserved' AS status;
