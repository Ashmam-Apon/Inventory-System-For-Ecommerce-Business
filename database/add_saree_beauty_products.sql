-- Add Saree and Beauty category products to TrackIt database
USE trackit;

-- Insert Saree products
INSERT INTO products (name, description, category, price, stock_quantity) VALUES
-- Traditional Sarees
('Banarasi Silk Saree', 'Handwoven Banarasi silk saree with golden zari work', 'Saree', 2499.00, 15),
('Kanjivaram Silk Saree', 'Pure Kanjivaram silk saree with temple border', 'Saree', 3999.00, 12),
('Cotton Handloom Saree', 'Pure cotton handloom saree with block print', 'Saree', 899.00, 25),
('Georgette Designer Saree', 'Designer georgette saree with embroidery work', 'Saree', 1599.00, 20),
('Chiffon Party Saree', 'Elegant chiffon saree perfect for parties', 'Saree', 1299.00, 18),

-- Casual and Daily Wear Sarees
('Cotton Tant Saree', 'Traditional Bengali tant saree in pure cotton', 'Saree', 699.00, 30),
('Linen Saree', 'Comfortable linen saree for daily wear', 'Saree', 799.00, 22),
('Printed Crepe Saree', 'Stylish printed crepe saree with digital print', 'Saree', 649.00, 35),
('Khadi Cotton Saree', 'Eco-friendly khadi cotton saree', 'Saree', 549.00, 28),
('Tussar Silk Saree', 'Natural tussar silk saree with minimal design', 'Saree', 1899.00, 16),

-- Festive and Wedding Sarees
('Net Lehenga Saree', 'Designer net lehenga style saree with heavy work', 'Saree', 4999.00, 8),
('Velvet Border Saree', 'Silk saree with rich velvet border', 'Saree', 2799.00, 14),
('Organza Tissue Saree', 'Lightweight organza tissue saree with gold thread', 'Saree', 1999.00, 18),
('Satin Silk Saree', 'Glossy satin silk saree with floral motifs', 'Saree', 1699.00, 20),
('Bandhani Saree', 'Traditional bandhani tie-dye saree from Gujarat', 'Saree', 1199.00, 25);

-- Insert Beauty products
INSERT INTO products (name, description, category, price, stock_quantity) VALUES
-- Skincare Products
('Vitamin C Serum', 'Brightening vitamin C serum for glowing skin', 'Beauty', 899.00, 45),
('Hyaluronic Acid Moisturizer', 'Deep hydration moisturizer with hyaluronic acid', 'Beauty', 1299.00, 38),
('Niacinamide Face Wash', 'Oil control face wash with niacinamide', 'Beauty', 499.00, 60),
('Retinol Night Cream', 'Anti-aging night cream with retinol', 'Beauty', 1599.00, 32),
('Sunscreen SPF 50', 'Broad spectrum sunscreen with SPF 50', 'Beauty', 649.00, 55),

-- Makeup Products
('Liquid Foundation', 'Full coverage liquid foundation - Multiple shades', 'Beauty', 1199.00, 42),
('Matte Lipstick Set', 'Set of 6 matte lipsticks in trending colors', 'Beauty', 999.00, 35),
('Eyeshadow Palette', '12-color eyeshadow palette with mirror', 'Beauty', 799.00, 28),
('Waterproof Mascara', 'Long-lasting waterproof mascara', 'Beauty', 599.00, 48),
('Concealer Stick', 'High coverage concealer stick', 'Beauty', 449.00, 52),

-- Hair Care Products
('Argan Oil Hair Serum', 'Nourishing argan oil serum for damaged hair', 'Beauty', 799.00, 40),
('Keratin Hair Mask', 'Deep conditioning keratin hair mask', 'Beauty', 899.00, 35),
('Dry Shampoo', 'Refreshing dry shampoo for oily hair', 'Beauty', 549.00, 45),
('Hair Growth Oil', 'Ayurvedic hair growth oil with natural herbs', 'Beauty', 699.00, 38),
('Leave-in Conditioner', 'Detangling leave-in conditioner spray', 'Beauty', 599.00, 42),

-- Fragrance and Body Care
('Perfume Gift Set', 'Set of 3 mini perfumes in elegant packaging', 'Beauty', 1499.00, 25),
('Body Lotion', 'Moisturizing body lotion with shea butter', 'Beauty', 399.00, 65),
('Body Scrub', 'Exfoliating coffee body scrub', 'Beauty', 549.00, 35),
('Hand Cream Set', 'Set of 5 travel-size hand creams', 'Beauty', 799.00, 30),
('Bath Bomb Set', 'Relaxing bath bomb set with essential oils', 'Beauty', 899.00, 28);

-- Update stock quantities to ensure good availability
UPDATE products SET stock_quantity = stock_quantity + 10 WHERE category IN ('Saree', 'Beauty');
