<?php
/**
 * Realistic Demo Seeder for ITER Faculty Profiles
 * Run via CLI: php database/seed_demo.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden: Seeder script can only be run from the command line interface.');
}

require_once __DIR__ . '/../config/database.php';

if (defined('APP_ENV') && APP_ENV === 'production') {
    die("Security Abort: Demo seed data cannot be executed in a production environment.\n");
}

echo "========================================================\n";
echo "  ITER Research Profile - Demo Faculty Seeder (Dev Only)\n";
echo "========================================================\n\n";

try {
    $db = Database::getConnection();

    // 1. Get Department IDs
    $deptStmt = $db->query("SELECT id, code FROM departments");
    $depts = $deptStmt->fetchAll(PDO::FETCH_KEY_PAIR); // code => id

    $cseId  = $depts['CSE'] ?? 1;
    $eceId  = $depts['ECE'] ?? 3;
    $eeId   = $depts['EE']  ?? 4;

    $demoPasswordHash = password_hash('Faculty@123', PASSWORD_DEFAULT);

    // 2. Demo Faculties
    $faculties = [
        [
            'name' => 'Dr. Debabrata Singh',
            'email' => 'debabrata.singh@iter.ac.in',
            'dept_id' => $cseId,
            'institution' => 'ITER, SOA Deemed to be University',
            'salutation' => 'Prof. Dr.',
            'designation' => 'Professor & Head',
            'cabin' => 'Block 1, Room 304, ITER',
            'phone' => '+91 674 2350181',
            'bio' => 'Dr. Debabrata Singh is a Professor in the Department of Computer Science & Engineering at ITER, SOA University. His research focuses on Machine Learning, Computer Vision, Deep Learning for Medical Diagnostics, and Edge AI. He has supervised 6 Ph.D. scholars and published over 60 articles in international journals.',
            'interests' => 'Machine Learning, Deep Learning, Medical Image Analysis, Edge Computing, Computer Vision',
            'scholar_url' => 'https://scholar.google.com/citations?user=sample1',
            'orcid' => '0000-0002-1825-0097',
            'scopus' => '57194512340',
            'citations' => 1845,
            'h_index' => 19,
            'i10_index' => 31,
            'publications' => [
                [
                    'title' => 'Deep Transfer Learning Architecture for Early Detection of Diabetic Retinopathy in Fundus Images',
                    'authors' => 'D. Singh, R. K. Patra, S. K. Mishra',
                    'type' => 'journal',
                    'venue' => 'IEEE Transactions on Medical Imaging',
                    'year' => 2024,
                    'volume' => '43',
                    'issue' => '4',
                    'pages' => '1120-1132',
                    'publisher' => 'IEEE',
                    'doi' => '10.1109/TMI.2024.3129841',
                    'indexing' => 'SCI / Scopus Q1',
                    'citations' => 42,
                    'abstract' => 'This paper presents an enhanced multi-scale convolutional architecture incorporating attention mechanisms for automated diabetic retinopathy classification.'
                ],
                [
                    'title' => 'Lightweight Edge-AI Framework for Real-Time Weed Detection in Precision Agriculture',
                    'authors' => 'D. Singh, A. Mohanty, P. Tripathy',
                    'type' => 'conference',
                    'venue' => 'IEEE International Conference on Advanced Computing (IACC)',
                    'year' => 2023,
                    'volume' => '1',
                    'issue' => '',
                    'pages' => '45-51',
                    'publisher' => 'IEEE',
                    'doi' => '10.1109/IACC.2023.1012398',
                    'indexing' => 'Scopus',
                    'citations' => 18,
                    'abstract' => 'We propose a quantized MobileNet backbone deployed on Raspberry Pi 4 edge nodes achieving 96.4% precision with 28 FPS inference.'
                ],
                [
                    'title' => 'Federated Learning for Privacy-Preserving Health Informatics: Architectures and Challenges',
                    'authors' => 'D. Singh, K. R. Nayak',
                    'type' => 'book_chapter',
                    'venue' => 'Advances in Healthcare Intelligence (Springer Book Series)',
                    'year' => 2022,
                    'volume' => '',
                    'issue' => '',
                    'pages' => '189-214',
                    'publisher' => 'Springer',
                    'doi' => '10.1007/978-981-16-1234-5_9',
                    'indexing' => 'Scopus Indexed Book Chapter',
                    'citations' => 27,
                    'abstract' => 'A comprehensive review of decentralized federated aggregation protocols applied to electronic health records across multi-hospital consortia.'
                ]
            ],
            'projects' => [
                [
                    'title' => 'Design of Edge-AI Embedded IoT Devices for Automated Crop Disease Diagnostic in Rural Odisha',
                    'agency' => 'DST (Department of Science & Technology), Govt. of India',
                    'code' => 'DST/TDT/AGRI/2023/89',
                    'role' => 'pi',
                    'amount' => 42.50,
                    'start_year' => 2023,
                    'end_year' => 2026,
                    'status' => 'ongoing'
                ]
            ],
            'patents' => [
                [
                    'title' => 'An Automated Intelligent Ophthalmic Diagnostic Imaging System',
                    'number' => 'IN202331045982',
                    'country' => 'India',
                    'status' => 'granted',
                    'filing_date' => '2023-04-12',
                    'grant_date' => '2024-02-18'
                ]
            ]
        ],
        [
            'name' => 'Dr. Priyadarshi Kanungo',
            'email' => 'priyadarshi.kanungo@iter.ac.in',
            'dept_id' => $eceId,
            'institution' => 'Institute of Technical Education and Research',
            'salutation' => 'Prof. Dr.',
            'designation' => 'Professor & Dean Research',
            'cabin' => 'Research Complex, Floor 2, ITER',
            'phone' => '+91 674 2350182',
            'bio' => 'Dr. Priyadarshi Kanungo is a senior professor in Electronics & Communication Engineering. His research areas include Evolutionary Optimization, Intelligent Signal Processing, VLSI System Design, and 5G/6G Wireless Antennas. He serves on editorial boards of leading IEEE journals.',
            'interests' => 'Signal Processing, 5G/6G Communications, Metaheuristic Optimization, VLSI Design, Antenna Arrays',
            'scholar_url' => 'https://scholar.google.com/citations?user=sample2',
            'orcid' => '0000-0003-4921-8721',
            'scopus' => '56218934200',
            'citations' => 2410,
            'h_index' => 24,
            'i10_index' => 45,
            'publications' => [
                [
                    'title' => 'Multi-Objective Grey Wolf Optimizer for Optimal Power Flow in Distributed Renewable Grids',
                    'authors' => 'P. Kanungo, S. Panda, P. K. Rout',
                    'type' => 'journal',
                    'venue' => 'Applied Soft Computing (Elsevier)',
                    'year' => 2023,
                    'volume' => '138',
                    'issue' => '',
                    'pages' => '110190',
                    'publisher' => 'Elsevier',
                    'doi' => '10.1016/j.asoc.2023.110190',
                    'indexing' => 'SCI Q1 / Scopus',
                    'citations' => 64,
                    'abstract' => 'This research develops a novel hybrid metaheuristic approach addressing non-linear transmission loss and voltage instability.'
                ]
            ],
            'projects' => [
                [
                    'title' => 'Development of Sub-THz Reconfigurable Intelligent Surfaces for 6G Wireless Backhaul',
                    'agency' => 'SERB (Science & Engineering Research Board), CRG Scheme',
                    'code' => 'CRG/2022/004812',
                    'role' => 'pi',
                    'amount' => 58.00,
                    'start_year' => 2022,
                    'end_year' => 2025,
                    'status' => 'ongoing'
                ]
            ],
            'patents' => [
                [
                    'title' => 'Reconfigurable Multi-Band Microstrip Patch Antenna for Satellite Ground Stations',
                    'number' => 'IN202231019284',
                    'country' => 'India',
                    'status' => 'granted',
                    'filing_date' => '2022-06-20',
                    'grant_date' => '2023-11-04'
                ]
            ]
        ],
        [
            'name' => 'Dr. Rasmita Dash',
            'email' => 'rasmita.dash@iter.ac.in',
            'dept_id' => $cseId,
            'institution' => 'Odisha University of Technology & Research (OUTR)',
            'salutation' => 'Dr.',
            'designation' => 'Associate Professor',
            'cabin' => 'Block 2, Room 108, OUTR',
            'phone' => '+91 674 2350183',
            'bio' => 'Dr. Rasmita Dash is an Associate Professor in Computer Science & Engineering. She received her Ph.D. in Computer Science with a focus on Computational Intelligence, Soft Computing, and Financial Stock Forecasting. She has published widely in Springer, Elsevier, and IEEE conferences.',
            'interests' => 'Computational Intelligence, Stock Market Forecasting, Evolutionary Algorithms, Neural Networks',
            'scholar_url' => 'https://scholar.google.com/citations?user=sample3',
            'orcid' => '0000-0001-7294-8219',
            'scopus' => '57201948301',
            'citations' => 920,
            'h_index' => 15,
            'i10_index' => 22,
            'publications' => [
                [
                    'title' => 'Comparative Analysis of Deep LSTM and Transformer Networks for High-Frequency Equity Forecasting',
                    'authors' => 'R. Dash, P. K. Dash, R. Bisoi',
                    'type' => 'journal',
                    'venue' => 'Expert Systems with Applications',
                    'year' => 2024,
                    'volume' => '237',
                    'issue' => 'Part B',
                    'pages' => '121540',
                    'publisher' => 'Elsevier',
                    'doi' => '10.1016/j.eswa.2023.121540',
                    'indexing' => 'SCI Q1',
                    'citations' => 31,
                    'abstract' => 'This paper explores the efficacy of self-attention mechanisms against recurrent models in chaotic non-stationary financial time series.'
                ]
            ],
            'projects' => [],
            'patents' => []
        ],
        [
            'name' => 'Dr. Mihir Narayan Mohanty',
            'email' => 'mihir.mohanty@nitrkl.ac.in',
            'dept_id' => $eeId,
            'institution' => 'National Institute of Technology (NIT) Rourkela',
            'salutation' => 'Prof. Dr.',
            'designation' => 'Professor & Senior Researcher',
            'cabin' => 'Department of Electrical Engineering, NIT Rourkela',
            'phone' => '+91 661 2462400',
            'bio' => 'Prof. Dr. Mihir Narayan Mohanty has decades of distinguished research in Biomedical Signal Processing, Cognitive Systems, Soft Computing, and Intelligent Instrumentation. He has published over 100 research articles and guided numerous doctoral scholars.',
            'interests' => 'Biomedical Signal Processing, Cognitive Computing, Pattern Recognition, Soft Computing, Smart Sensors',
            'scholar_url' => 'https://scholar.google.com/citations?user=sample4',
            'orcid' => '0000-0002-3914-7210',
            'scopus' => '55194830112',
            'citations' => 3140,
            'h_index' => 29,
            'i10_index' => 52,
            'publications' => [
                [
                    'title' => 'Real-time Wavelet-based Cardiac Arrhythmia Classification using Deep Residual Networks',
                    'authors' => 'M. N. Mohanty, S. Rout, K. Parida',
                    'type' => 'journal',
                    'venue' => 'Biomedical Signal Processing and Control (Elsevier)',
                    'year' => 2024,
                    'volume' => '88',
                    'issue' => '',
                    'pages' => '105432',
                    'publisher' => 'Elsevier',
                    'doi' => '10.1016/j.bspc.2023.105432',
                    'indexing' => 'SCI Q1 / Scopus',
                    'citations' => 48,
                    'abstract' => 'An efficient 1D ResNet model coupled with discrete wavelet transform for multi-lead ECG rhythm classification.'
                ]
            ],
            'projects' => [
                [
                    'title' => 'Non-Invasive Brain-Computer Interface for Assistive Robotic Control',
                    'agency' => 'DRDO (Defence Research and Development Organisation)',
                    'code' => 'DRDO/ERIP/2023/14',
                    'role' => 'pi',
                    'amount' => 74.00,
                    'start_year' => 2023,
                    'end_year' => 2026,
                    'status' => 'ongoing'
                ]
            ],
            'patents' => []
        ]
    ];

    foreach ($faculties as $fData) {
        // Insert user
        $uStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $uStmt->execute([$fData['email']]);
        $existing = $uStmt->fetch();

        if (!$existing) {
            $insUser = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, status) VALUES (?, ?, ?, 'faculty', 'active')");
            $insUser->execute([$fData['email'], $demoPasswordHash, $fData['name']]);
            $userId = (int)$db->lastInsertId();
        } else {
            $userId = (int)$existing['id'];
        }

        // Insert or update faculty_profile
        $pStmt = $db->prepare("SELECT id FROM faculty_profiles WHERE user_id = ?");
        $pStmt->execute([$userId]);
        $prof = $pStmt->fetch();

        if (!$prof) {
            $insProf = $db->prepare("
                INSERT INTO faculty_profiles (
                    user_id, department_id, institution, salutation, designation, cabin, phone, bio, 
                    research_interests, google_scholar_url, orcid_id, scopus_id, 
                    total_citations, h_index, i10_index, is_verified
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $insProf->execute([
                $userId, $fData['dept_id'], $fData['institution'] ?? 'ITER, SOA University',
                $fData['salutation'], $fData['designation'],
                $fData['cabin'], $fData['phone'], $fData['bio'], $fData['interests'],
                $fData['scholar_url'], $fData['orcid'], $fData['scopus'],
                $fData['citations'], $fData['h_index'], $fData['i10_index']
            ]);
            $profId = (int)$db->lastInsertId();
        } else {
            $profId = (int)$prof['id'];
            if (!empty($fData['institution'])) {
                $updProf = $db->prepare("UPDATE faculty_profiles SET institution = ? WHERE id = ?");
                $updProf->execute([$fData['institution'], $profId]);
            }
        }

        // Publications
        foreach ($fData['publications'] as $pub) {
            $chkPub = $db->prepare("SELECT id FROM publications WHERE faculty_profile_id = ? AND title = ?");
            $chkPub->execute([$profId, $pub['title']]);
            if (!$chkPub->fetch()) {
                $insPub = $db->prepare("
                    INSERT INTO publications (
                        faculty_profile_id, title, authors, publication_type, journal_conference_name,
                        publication_year, volume, issue, pages, publisher, doi, abstract, indexing,
                        citation_count, created_by_user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insPub->execute([
                    $profId, $pub['title'], $pub['authors'], $pub['type'], $pub['venue'],
                    $pub['year'], $pub['volume'], $pub['issue'], $pub['pages'], $pub['publisher'],
                    $pub['doi'], $pub['abstract'], $pub['indexing'], $pub['citations'], $userId
                ]);
            }
        }

        // Projects
        foreach ($fData['projects'] as $proj) {
            $chkProj = $db->prepare("SELECT id FROM projects WHERE faculty_profile_id = ? AND title = ?");
            $chkProj->execute([$profId, $proj['title']]);
            if (!$chkProj->fetch()) {
                $insProj = $db->prepare("
                    INSERT INTO projects (
                        faculty_profile_id, title, funding_agency, project_code, role,
                        amount_lakhs, start_year, end_year, status, created_by_user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insProj->execute([
                    $profId, $proj['title'], $proj['agency'], $proj['code'], $proj['role'],
                    $proj['amount'], $proj['start_year'], $proj['end_year'], $proj['status'], $userId
                ]);
            }
        }

        // Patents
        foreach ($fData['patents'] as $pat) {
            $chkPat = $db->prepare("SELECT id FROM patents WHERE faculty_profile_id = ? AND title = ?");
            $chkPat->execute([$profId, $pat['title']]);
            if (!$chkPat->fetch()) {
                $insPat = $db->prepare("
                    INSERT INTO patents (
                        faculty_profile_id, title, patent_number, country, status,
                        filing_date, grant_date, created_by_user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insPat->execute([
                    $profId, $pat['title'], $pat['number'], $pat['country'], $pat['status'],
                    $pat['filing_date'], $pat['grant_date'], $userId
                ]);
            }
        }
    }

    // 3. Seed an Assistant / Delegate User and grant access to Dr. Debabrata Singh
    $asstEmail = 'assistant.cse@iter.ac.in';
    $uStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $uStmt->execute([$asstEmail]);
    $asstUser = $uStmt->fetch();

    if (!$asstUser) {
        $insAsst = $db->prepare("INSERT INTO users (email, password_hash, full_name, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $insAsst->execute([$asstEmail, $demoPasswordHash, 'Pooja Mohapatra (CSE Research Assistant)']);
        $asstId = (int)$db->lastInsertId();
    } else {
        $asstId = (int)$asstUser['id'];
    }

    // Assign Dr. Debabrata Singh to this assistant
    $debStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $debStmt->execute(['debabrata.singh@iter.ac.in']);
    $debUser = $debStmt->fetch();

    if ($debUser) {
        $debUserId = (int)$debUser['id'];
        $delStmt = $db->prepare("INSERT IGNORE INTO faculty_delegates (faculty_user_id, delegate_user_id, granted_by) VALUES (?, ?, ?)");
        $delStmt->execute([$debUserId, $asstId, $debUserId]);
    }

    echo "Demo faculty data and Assistant delegation seeded successfully!\n";
    echo "========================================================\n";
    echo "Development Demo Faculty Accounts Seeded (Local Dev Only):\n";
    echo "1. Faculty:   debabrata.singh@iter.ac.in (Prof. & Head, CSE)\n";
    echo "2. Faculty:   priyadarshi.kanungo@iter.ac.in (Dean Research, ECE)\n";
    echo "3. Faculty:   rasmita.dash@iter.ac.in (Assoc. Prof., CSE)\n";
    echo "4. Assistant: assistant.cse@iter.ac.in (Research Delegate for Dr. Debabrata Singh)\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
