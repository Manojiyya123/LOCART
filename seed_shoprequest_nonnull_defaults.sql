-- Insert 5 shoprequest rows with no NULL values and omit `status` so the table default applies
-- Adjust shopid/ownerid/verification_id values as needed for your environment

INSERT INTO shoprequest (shopid, name, ownerid, type, contact_no1, contact_no2, verification_id, city, pincode, password, about, request_received_date)
VALUES
(1011, 'Sunrise Grocery', 1, 'Grocery', '03001111011', '03001111012', 1, 'Karachi', '74210', 'sun123', 'Neighborhood grocery store', '2025-10-10 08:00:00'),
(1012, 'Digital Depot', 2, 'Electronics', '03002222011', '03002222012', 2, 'Lahore', '54010', 'digidep', 'Electronics and accessories', '2025-10-10 09:15:00'),
(1013, 'Morning Bakery', 3, 'Bakery', '03003333011', '03003333012', 3, 'Islamabad', '44010', 'mrbake', 'Fresh breads and pastries', '2025-10-10 10:30:00'),
(1014, 'Style Avenue', 4, 'Clothing', '03004444011', '03004444012', 4, 'Peshawar', '25010', 'styleav', 'Clothing and apparel', '2025-10-10 11:45:00'),
(1015, 'Handy Tools', 5, 'Hardware', '03005555011', '03005555012', 5, 'Quetta', '87310', 'handytools', 'Tools and hardware supplies', '2025-10-10 13:00:00');

-- End of file
