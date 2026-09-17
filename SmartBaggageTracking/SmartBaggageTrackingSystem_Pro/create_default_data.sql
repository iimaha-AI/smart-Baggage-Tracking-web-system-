-- Create default data required for flight management system
-- This file should be run after creating the basic database

USE smart_baggage_pro;

-- Add airports if they don't exist
INSERT IGNORE INTO airports (airport_code, airport_name, city, country, timezone, status) VALUES
('RUH', 'King Khalid International Airport', 'Riyadh', 'Saudi Arabia', 'Asia/Riyadh', 'active'),
('JED', 'King Abdulaziz International Airport', 'Jeddah', 'Saudi Arabia', 'Asia/Riyadh', 'active'),
('DMM', 'King Fahd International Airport', 'Dammam', 'Saudi Arabia', 'Asia/Riyadh', 'active'),
('DXB', 'Dubai International Airport', 'Dubai', 'UAE', 'Asia/Dubai', 'active'),
('AUH', 'Abu Dhabi International Airport', 'Abu Dhabi', 'UAE', 'Asia/Dubai', 'active'),
('DOH', 'Hamad International Airport', 'Doha', 'Qatar', 'Asia/Qatar', 'active'),
('KWI', 'Kuwait International Airport', 'Kuwait City', 'Kuwait', 'Asia/Kuwait', 'active');

-- Add terminals for each airport
INSERT IGNORE INTO terminals (airport_id, terminal_code, terminal_name, description, status) 
SELECT 
    a.id,
    'T1',
    'Terminal 1',
    'Main terminal building',
    'active'
FROM airports a 
WHERE a.airport_code IN ('RUH', 'JED', 'DMM', 'DXB', 'AUH', 'DOH', 'KWI')
AND NOT EXISTS (
    SELECT 1 FROM terminals t WHERE t.airport_id = a.id AND t.terminal_code = 'T1'
);

-- Add default gates for each terminal
INSERT IGNORE INTO gates (terminal_id, gate_number, gate_type, status)
SELECT 
    t.id,
    'DEFAULT',
    'domestic',
    'active'
FROM terminals t
WHERE t.terminal_code = 'T1'
AND NOT EXISTS (
    SELECT 1 FROM gates g WHERE g.terminal_id = t.id AND g.gate_number = 'DEFAULT'
);

-- Add additional gates for major airports
INSERT IGNORE INTO gates (terminal_id, gate_number, gate_type, status)
SELECT 
    t.id,
    CONCAT('A', LPAD(n.n, 2, '0')),
    'domestic',
    'active'
FROM terminals t
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
) n
WHERE t.terminal_code = 'T1'
AND EXISTS (
    SELECT 1 FROM airports a WHERE a.id = t.airport_id 
    AND a.airport_code IN ('RUH', 'JED', 'DXB', 'AUH', 'DOH')
)
AND NOT EXISTS (
    SELECT 1 FROM gates g WHERE g.terminal_id = t.id AND g.gate_number = CONCAT('A', LPAD(n.n, 2, '0'))
);

-- Add international gates for major airports
INSERT IGNORE INTO gates (terminal_id, gate_number, gate_type, status)
SELECT 
    t.id,
    CONCAT('I', LPAD(n.n, 2, '0')),
    'international',
    'active'
FROM terminals t
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) n
WHERE t.terminal_code = 'T1'
AND EXISTS (
    SELECT 1 FROM airports a WHERE a.id = t.airport_id 
    AND a.airport_code IN ('RUH', 'JED', 'DXB', 'AUH', 'DOH')
)
AND NOT EXISTS (
    SELECT 1 FROM gates g WHERE g.terminal_id = t.id AND g.gate_number = CONCAT('I', LPAD(n.n, 2, '0'))
);

-- Display results
SELECT '✅ Basic data created successfully!' AS message;

-- Display statistics of added data
SELECT 
    'Airports' as table_name,
    COUNT(*) as count
FROM airports
WHERE status = 'active'

UNION ALL

SELECT 
    'Terminals' as table_name,
    COUNT(*) as count
FROM terminals
WHERE status = 'active'

UNION ALL

SELECT 
    'Gates' as table_name,
    COUNT(*) as count
FROM gates
WHERE status = 'active';
