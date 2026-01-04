<?php
require_once '../core/auth.php';
require_admin();

$message = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    
    // Simple image upload (optional for now, just storing name if no file)
    $image = ''; 
    // TODO: Implement file upload

    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, image) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$name, $slug, $image]);
        $message = "Category added successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit;
}

require_once __DIR__ . '/partials/sidebar.php';

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Categories - TechHat Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }
        .admin-content {
            margin-left: 280px;
            transition: margin-left 0.3s;
        }
        .content { padding: 30px; }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Add Category Form */
        .add-category-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .add-category-form h3 {
            margin: 0 0 20px 0;
            color: white;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .form-group input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
        }
        .form-group button {
            padding: 14px 32px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .category-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .category-name {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .category-slug {
            font-size: 13px;
            color: #7f8c8d;
            background: #ecf0f1;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-family: monospace;
        }
        
        .category-id {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .category-actions {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid #ecf0f1;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .empty-state i {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            font-size: 24px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .admin-content { margin-left: 0; }
            .categories-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <div class="content">
            <div class="page-header">
                <h1>
                    <i class="bi bi-grid-fill"></i>
                    Categories
                </h1>
            </div>
            
            <?php if ($message): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="add-category-form">
                <h3>
                    <i class="bi bi-plus-circle"></i>
                    Add New Category
                </h3>
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Enter category name..." required>
                        <button type="submit" name="add_category">
                            <i class="bi bi-save"></i>
                            Add Category
                        </button>
                    </div>
                </form>
            </div>

            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <h3>No Categories Yet</h3>
                    <p>Create your first category above</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-card">
                            <span class="category-id">#<?php echo $cat['id']; ?></span>
                            
                            <div class="category-icon">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            
                            <h3 class="category-name"><?php echo htmlspecialchars($cat['name']); ?></h3>
                            
                            <span class="category-slug">
                                <i class="bi bi-link-45deg"></i>
                                <?php echo $cat['slug']; ?>
                            </span>
                            
                            <div class="category-actions">
                                <a href="categories.php?delete=<?php echo $cat['id']; ?>" 
                                   class="btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                    <i class="bi bi-trash"></i>
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>