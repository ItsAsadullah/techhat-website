-- Create expenses table for accounts management
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO expenses (description, amount, category, expense_date) VALUES
('Office Rent - January', 25000.00, 'Rent', '2026-01-01'),
('Electricity Bill', 3500.00, 'Utilities', '2026-01-02'),
('Marketing Campaign', 8000.00, 'Marketing', '2026-01-03');
