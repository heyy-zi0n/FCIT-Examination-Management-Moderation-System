<?php
/**
 * Migration Script: Update Departments UI Data
 */
require_once __DIR__ . '/../bootstrap.php';
$pdo = getDb();

try {
    $pdo->exec("ALTER TABLE `departments` ADD COLUMN `description` TEXT NULL");
    echo "Added description column.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Description column already exists.\n";
    } else {
        throw $e;
    }
}

// First rename IST -> ICT and AID -> DSC to avoid losing tied relationships
$pdo->exec("UPDATE departments SET code = 'ICT' WHERE code = 'IST'");
$pdo->exec("UPDATE departments SET code = 'DSC' WHERE code = 'AID'");

// Map of code -> [name, description]
$updates = [
    'CSC' => [
        'name' => 'Computer Science',
        'description' => 'The Department of Computer Science offers comprehensive educational programs focused on the design, analysis, and implementation of computer networks, architectures, and algorithms.'
    ],
    'CYB' => [
        'name' => 'Cyber Security',
        'description' => 'The Department of Cyber Security is dedicated to producing professionals trained in protecting network systems, digital assets, and critical infrastructures.'
    ],
    'DSC' => [
        'name' => 'Data Science',
        'description' => 'The Department of Data Science provides state-of-the-art training in statistical analysis, machine learning, data visualization, and big data technologies.'
    ],
    'ICT' => [
        'name' => 'Information and Communication Technology (ICT)',
        'description' => 'The Department of ICT focuses on the integration of telecommunications, computer networks, and enterprise software to enable organizations to operate effectively.'
    ],
    'SEN' => [
        'name' => 'Software Engineering',
        'description' => 'The Department of Software Engineering focuses on the systematic application of engineering principles to the development, operation, and maintenance of software systems.'
    ]
];

$stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE code = ?");
foreach ($updates as $code => $data) {
    $stmt->execute([$data['name'], $data['description'], $code]);
}

echo "Departments updated successfully!\n";
