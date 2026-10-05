<?php
/**
 * Database Migration & Initial Seeder Script
 * Run via CLI: php database/migrate.php
 */

require_once __DIR__ . '/../config/database.php';

echo "========================================================\n";
echo "  ITER Research Profile - Database Migration & Seeding  \n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();
    echo "[1/4] Connected to database: " . DB_NAME . "\n";

    // 1. Read schema SQL
    $schemaPath = __DIR__ . '/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception("schema.sql not found at: {$schemaPath}");
    }
    $sql = file_get_contents($schemaPath);
    echo "[2/4] Executing schema.sql (11 tables)... ";
    $db->exec($sql);
    echo "DONE.\n";

    // 2. Seed ITER Departments
    echo "[3/4] Seeding standard ITER academic departments... ";
    $departments = [
        ['code' => 'CSE',  'name' => 'Computer Science & Engineering', 'description' => 'Department of Computer Science and Engineering, ITER'],
        ['code' => 'CSIT', 'name' => 'Computer Science & Information Technology', 'description' => 'Department of CS & IT, ITER'],
        ['code' => 'ECE',  'name' => 'Electronics & Communication Engineering', 'description' => 'Department of ECE, ITER'],
        ['code' => 'EE',   'name' => 'Electrical Engineering', 'description' => 'Department of Electrical Engineering, ITER'],
        ['code' => 'EEE',  'name' => 'Electrical & Electronics Engineering', 'description' => 'Department of EEE, ITER'],
        ['code' => 'ME',   'name' => 'Mechanical Engineering', 'description' => 'Department of Mechanical Engineering, ITER'],
        ['code' => 'CE',   'name' => 'Civil Engineering', 'description' => 'Department of Civil Engineering, ITER'],
        ['code' => 'CA',   'name' => 'Computer Applications (MCA/BCA)', 'description' => 'Department of Computer Applications, ITER'],
    ];

    $deptStmt = $db->prepare("INSERT INTO departments (code, name, description) VALUES (:code, :name, :description) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");
    foreach ($departments as $dept) {
        $deptStmt->execute([
            ':code'        => $dept['code'],
            ':name'        => $dept['name'],
            ':description' => $dept['description'],
        ]);
    }
    echo "DONE (" . count($departments) . " departments seeded).\n";

    // 3. Seed Default Super Admin Account
    echo "[4/4] Seeding initial Super Admin account... ";
    $adminEmail = 'superadmin@iter.ac.in';
    $adminPass  = 'AdminPassword@123';
    $adminHash  = password_hash($adminPass, PASSWORD_DEFAULT);
    $adminName  = 'Dr. ITER Super Administrator';

    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$adminEmail]);
    if (!$checkStmt->fetch()) {
        $userStmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, status) VALUES (?, ?, ?, 'super_admin', 'active')");
        $userStmt->execute([$adminEmail, $adminHash, $adminName]);
        echo "CREATED.\n";
    } else {
        echo "EXISTS.\n";
    }

    echo "\n========================================================\n";
    echo "  Migration Completed Successfully!                      \n";
    echo "  Super Admin Email:    {$adminEmail}                    \n";
    echo "  Super Admin Password: {$adminPass}                     \n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
