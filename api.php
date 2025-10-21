<?php
// api.php - Menggunakan PDO untuk koneksi dan semua fungsionalitas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request (Penting untuk PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- PENGATURAN KONEKSI DATABASE ---
$host = 'localhost'; 
$db = 'delta_db';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => "Koneksi database gagal: " . $e->getMessage()]));
}

// --- LOGIKA API ---
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Aksi tidak valid.'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    
    // =========================================================================
    // 0. LOGIN ADMIN (Untuk flow client side)
    // =========================================================================
    case 'login_admin':
        if ($method !== 'POST') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan.'];
            break;
        }
        $response = ['success' => true, 'message' => 'Login berhasil!'];
        break;

    // =========================================================================
    // 1. GET MASTER DATA (Read)
    // =========================================================================
    case 'get_master_data':
        $type = $_GET['type'] ?? '';
        $tableName = '';
        $nameColumn = '';
        
        if ($type === 'desa') {
            $tableName = 'master_desa';
            $nameColumn = 'nama_desa';
        } elseif ($type === 'pemeriksaan') {
            $tableName = 'master_pemeriksaan';
            $nameColumn = 'nama_pemeriksaan';
        } else {
            $response = ['success' => false, 'message' => 'Tipe master data tidak valid.'];
            break;
        }

        try {
            // Mengurutkan berdasarkan ID agar data baru muncul di akhir.
            $stmt = $pdo->prepare("SELECT id, $nameColumn as name FROM $tableName ORDER BY id");
            $stmt->execute();
            $data = $stmt->fetchAll();
            $response = ['success' => true, 'message' => 'Data master berhasil diambil.', 'data' => $data];
        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal mengambil data master: ' . $e->getMessage()];
        }
        break;

    // =========================================================================
    // 2. CEK LAPORAN (Deteksi Mode Tambah/Edit)
    // =========================================================================
    case 'get_laporan_by_periode':
        $tahun = $_GET['tahun'] ?? null;
        $bulan = $_GET['bulan'] ?? null;
        $jenis = $_GET['jenis'] ?? null;

        if (!$tahun || !$bulan || !$jenis) {
            $response = ['success' => false, 'message' => 'Parameter periode tidak lengkap.'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, data FROM laporan_bulanan WHERE periode_tahun = ? AND periode_bulan = ? AND jenis_data = ?");
            // Binding values as integers
            $stmt->execute([(int)$tahun, (int)$bulan, $jenis]); 
            $laporan = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($laporan) {
                $response = [
                    'success' => true,
                    'message' => 'Laporan ditemukan. Beralih ke Mode Edit.',
                    'data_laporan' => [
                        'id' => $laporan['id'],
                        'data' => json_decode($laporan['data'], true) 
                    ]
                ];
            } else {
                $response = [
                    'success' => true,
                    'message' => 'Laporan belum ada. Beralih ke Mode Tambah.',
                    'data_laporan' => null
                ];
            }
        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal memeriksa status laporan: ' . $e->getMessage()];
        }
        break;

    // =========================================================================
    // 3. INPUT LAPORAN BARU (Create)
    // =========================================================================
    case 'input_laporan':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan.'];
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $tahun = $data['periode_tahun'] ?? null;
        $bulan = $data['periode_bulan'] ?? null;
        $jenis = $data['jenis_data'] ?? null;
        $dataKasus = $data['data_kasus'] ?? null;
        
        if (!$tahun || !$bulan || !$jenis || !$dataKasus) {
            $response = ['success' => false, 'message' => 'Data input tidak lengkap.'];
            break;
        }

        try {
            // Cek duplikasi
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM laporan_bulanan WHERE periode_tahun = ? AND periode_bulan = ? AND jenis_data = ?");
            $stmtCheck->execute([(int)$tahun, (int)$bulan, $jenis]);
            if ($stmtCheck->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Laporan untuk periode ini sudah ada. Gunakan tombol UBAH DATA.'];
                break;
            }
            
            $sql = "INSERT INTO laporan_bulanan (periode_bulan, periode_tahun, jenis_data, tanggal_input, data, user_input) VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            $jsonKasus = json_encode($dataKasus); 
            $userInput = 'admin_test'; 
            
            // Binding values as integers for bulan/tahun
            $stmt->execute([(int)$bulan, (int)$tahun, $jenis, $jsonKasus, $userInput]);
            $response = ['success' => true, 'message' => 'Data laporan berhasil disimpan!', 'laporan_id' => $pdo->lastInsertId()];

        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal menyimpan data laporan: ' . $e->getMessage()];
        }
        break;

    // =========================================================================
    // 4. UPDATE LAPORAN (Update)
    // =========================================================================
    case 'update_laporan':
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan.'];
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        $laporanId = $data['laporan_id'] ?? null;
        $dataKasus = $data['data_kasus'] ?? null;

        if (!$laporanId || !$dataKasus) {
            $response = ['success' => false, 'message' => 'ID Laporan atau Data Kasus tidak lengkap.'];
            break;
        }

        try {
            $sql = "UPDATE laporan_bulanan SET data = ?, tanggal_input = NOW(), user_input = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            $jsonKasus = json_encode($dataKasus); 
            $userInput = 'admin_test'; 
            
            // Binding ID as integer
            $stmt->execute([$jsonKasus, $userInput, (int)$laporanId]);
            $response = ['success' => true, 'message' => 'Data laporan berhasil diperbarui!'];

        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal memperbarui data laporan: ' . $e->getMessage()];
        }
        break;
        
    // =========================================================================
    // 5. GET DASHBOARD DATA (Riwayat dengan filter) - FIX FILTER
    // =========================================================================
    case 'get_dashboard_data':
        $tahun = $_GET['tahun'] ?? '';
        $bulan = $_GET['bulan'] ?? '';
        
        $params = [];
        $where_conditions = ["1=1"]; 

        // Filter Tahun
        // Harus berupa angka dan tidak boleh string kosong (jika filter "Semua Tahun" tidak dipilih)
        if (!empty($tahun) && is_numeric($tahun)) {
            $where_conditions[] = "periode_tahun = ?";
            $params[] = (int)$tahun; 
        }

        // Filter Bulan
        // Harus berupa angka dan lebih besar dari 0 (asumsi 0 adalah value untuk 'Semua Bulan')
        if (!empty($bulan) && is_numeric($bulan) && (int)$bulan > 0) {
            $where_conditions[] = "periode_bulan = ?";
            $params[] = (int)$bulan; 
        }

        $where_clause = "WHERE " . implode(' AND ', $where_conditions);

        try {
            // Query untuk mengambil semua riwayat terbaru
            $sql = "SELECT id, jenis_data as type, periode_bulan as bulan, periode_tahun as tahun, 
                            DATE_FORMAT(tanggal_input, '%d-%m-%Y %H:%i') as waktu_input 
                            FROM laporan_bulanan 
                            $where_clause 
                            ORDER BY tanggal_input DESC 
                            LIMIT 100"; 
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $latestInput = $stmt->fetchAll();
            
            $response = [
                'success' => true, 
                'message' => 'Data dashboard berhasil diambil.',
                'data' => [
                    'latest_input' => $latestInput
                ]
            ];
        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal mengambil data dashboard: ' . $e->getMessage()];
        }
        break;
        
    // =========================================================================
    // 7. GET VISUALISASI DATA (Action untuk visualisasi_data.html)
    // =========================================================================
    case 'get_visualisasi_data':
        $tahun = $_GET['tahun'] ?? '';
        $bulan = $_GET['bulan'] ?? '';

        $params = [];
        $where_conditions = ["1=1"];
        
        // Filter Tahun
        if (!empty($tahun) && is_numeric($tahun)) {
            $where_conditions[] = "periode_tahun = ?";
            $params[] = (int)$tahun;
        }

        // Filter Bulan
        if (!empty($bulan) && is_numeric($bulan) && (int)$bulan > 0) {
            $where_conditions[] = "periode_bulan = ?";
            $params[] = (int)$bulan;
        }

        $where_clause = "WHERE " . implode(' AND ', $where_conditions);

        try {
            // Mengambil semua data laporan yang dibutuhkan
            $sql = "SELECT jenis_data, data FROM laporan_bulanan $where_clause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawReports = $stmt->fetchAll();
            
            // Mengumpulkan semua data kasus/pemeriksaan
            $aggregatedData = [
                'desa' => [], 
                'pemeriksaan' => []
            ];

            foreach ($rawReports as $report) {
                $jenis = $report['jenis_data'];
                $dataJson = json_decode($report['data'], true);
                
                if ($dataJson) {
                    if (isset($aggregatedData[$jenis])) {
                        foreach ($dataJson as $key => $value) {
                            // Key format: data_ID
                            $id = substr($key, 5); 
                            $currentCount = $aggregatedData[$jenis][$id] ?? 0;
                            $aggregatedData[$jenis][$id] = $currentCount + $value;
                        }
                    }
                }
            }
            
            $response = ['success' => true, 'message' => 'Data visualisasi berhasil diagregasi.', 'data' => $aggregatedData];

        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal mengambil data visualisasi: ' . $e->getMessage()];
        }
        break;
        
    // =========================================================================
    // 6. FUNGSI TAMBAHAN ADMIN MASTER DATA (Create/Delete)
    // =========================================================================
    case 'add_master_data':
        if ($method !== 'POST') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan POST.'];
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        $nama = $input['nama'] ?? '';

        if (empty($type) || empty($nama)) {
            $response = ['success' => false, 'message' => 'Data tidak lengkap.'];
            break;
        }

        $tableName = ($type === 'desa') ? 'master_desa' : (($type === 'pemeriksaan') ? 'master_pemeriksaan' : '');
        $columnName = ($type === 'desa') ? 'nama_desa' : (($type === 'pemeriksaan') ? 'nama_pemeriksaan' : '');

        if (!$tableName) {
            $response = ['success' => false, 'message' => 'Tipe master data tidak valid.'];
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO $tableName ($columnName) VALUES (?)");
            $stmt->execute([$nama]);
            $response = ['success' => true, 'message' => 'Data master berhasil ditambahkan.', 'new_id' => $pdo->lastInsertId()];
        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal menambahkan data: ' . $e->getMessage()];
        }
        break;

    // =========================================================================
    // 8. UPDATE NAMA MASTER DATA (Edit)
    // =========================================================================
    case 'update_master_data_name':
        if ($method !== 'PUT') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan PUT.'];
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = $data['id'] ?? null;
        $type = $data['type'] ?? '';
        $nama_baru = $data['nama'] ?? '';

        if (empty($id) || empty($type) || empty($nama_baru)) {
            $response = ['success' => false, 'message' => 'ID, tipe, atau nama baru tidak lengkap.'];
            break;
        }

        $tableName = '';
        $columnName = '';

        if ($type === 'desa') {
            $tableName = 'master_desa';
            $columnName = 'nama_desa';
        } elseif ($type === 'pemeriksaan') {
            $tableName = 'master_pemeriksaan';
            $columnName = 'nama_pemeriksaan';
        } else {
            $response = ['success' => false, 'message' => 'Tipe master data tidak valid.'];
            break;
        }

        try {
            $sql = "UPDATE $tableName SET $columnName = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            // Binding ID as integer
            $stmt->execute([$nama_baru, (int)$id]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Nama master data berhasil diperbarui.'];
            } else {
                $response = ['success' => false, 'message' => 'Data tidak ditemukan atau tidak ada perubahan.'];
            }

        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal memperbarui data: ' . $e->getMessage()];
        }
        break;


    case 'delete_master_data':
        if ($method !== 'DELETE') {
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan DELETE.'];
            break;
        }
        $id = $_GET['id'] ?? null;
        $type = $_GET['type'] ?? '';

        if (empty($id) || empty($type)) {
            $response = ['success' => false, 'message' => 'ID atau tipe tidak ditemukan.'];
            break;
        }

        $tableName = ($type === 'desa') ? 'master_desa' : (($type === 'pemeriksaan') ? 'master_pemeriksaan' : '');
        
        try {
            $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = ?");
            // Binding ID as integer
            $stmt->execute([(int)$id]);
            $response = ['success' => true, 'message' => 'Data master berhasil dihapus.'];
        } catch (\PDOException $e) {
            $response = ['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()];
        }
        break;

    default:
        http_response_code(400); 
        $response = ['success' => false, 'message' => 'Aksi API tidak dikenali.'];
        break;
}

echo json_encode($response);