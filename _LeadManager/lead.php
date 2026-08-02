<?php
// =====================================================================
// LEAD MANAGER PRO — lead.php  (Fixed: create_date + expiry_date import)
// =====================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_NAME', 'Lead Manager Pro');
define('APP_ENV',  'prod');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (!session_id()) session_start();

$conn = new mysqli('localhost', 'u743928828_lead_user', 'Admin_66666', 'u743928828_lead_db');
$conn->set_charset("utf8mb4");

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function intval_safe($v): int  { return max(0, (int)$v); }
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
$stateCities = [
    "Andhra Pradesh"       => ["Visakhapatnam","Vijayawada","Guntur","Nellore","Kurnool","Tirupati"],
    "Arunachal Pradesh"    => ["Itanagar","Naharlagun","Pasighat"],
    "Assam"                => ["Guwahati","Silchar","Dibrugarh","Jorhat","Nagaon","Tinsukia"],
    "Bihar"                => ["Patna","Gaya","Muzaffarpur","Bhagalpur","Darbhanga","Purnia"],
    "Chhattisgarh"         => ["Raipur","Bhilai","Bilaspur","Korba","Durg"],
    "Goa"                  => ["Panaji","Margao","Vasco da Gama","Mapusa"],
    "Gujarat"              => ["Ahmedabad","Surat","Vadodara","Rajkot","Bhavnagar","Jamnagar","Gandhinagar"],
    "Haryana"              => ["Faridabad","Gurgaon","Panipat","Ambala","Rohtak","Hisar","Karnal"],
    "Himachal Pradesh"     => ["Shimla","Manali","Dharamshala","Solan","Mandi"],
    "Jharkhand"            => ["Ranchi","Jamshedpur","Dhanbad","Bokaro","Hazaribagh"],
    "Karnataka"            => ["Bangalore","Mysore","Hubli","Mangalore","Belgaum","Davangere"],
    "Kerala"               => ["Thiruvananthapuram","Kochi","Kozhikode","Kannur","Kollam","Thrissur"],
    "Madhya Pradesh"       => ["Bhopal","Indore","Jabalpur","Gwalior","Ujjain","Sagar"],
    "Maharashtra"          => ["Mumbai","Pune","Nagpur","Thane","Nashik","Aurangabad","Solapur","Navi Mumbai","Amravati"],
    "Manipur"              => ["Imphal","Thoubal","Bishnupur"],
    "Meghalaya"            => ["Shillong","Tura","Jowai"],
    "Mizoram"              => ["Aizawl","Lunglei","Champhai"],
    "Nagaland"             => ["Kohima","Dimapur","Mokokchung"],
    "Odisha"               => ["Bhubaneswar","Cuttack","Rourkela","Sambalpur","Berhampur"],
    "Punjab"               => ["Ludhiana","Amritsar","Jalandhar","Patiala","Bathinda","Mohali"],
    "Rajasthan"            => ["Jaipur","Jodhpur","Udaipur","Kota","Ajmer","Bikaner","Alwar"],
    "Sikkim"               => ["Gangtok","Namchi","Mangan"],
    "Tamil Nadu"           => ["Chennai","Coimbatore","Madurai","Tiruchirappalli","Salem","Erode","Vellore"],
    "Telangana"            => ["Hyderabad","Warangal","Nizamabad","Karimnagar","Khammam"],
    "Tripura"              => ["Agartala","Udaipur","Dharmanagar"],
    "Uttar Pradesh"        => ["Lucknow","Kanpur","Agra","Varanasi","Meerut","Allahabad","Ghaziabad","Noida","Bareilly","Gorakhpur"],
    "Uttarakhand"          => ["Dehradun","Haridwar","Roorkee","Haldwani","Rishikesh","Nainital"],
    "West Bengal"          => ["Kolkata","Howrah","Durgapur","Asansol","Siliguri","Bardhaman"],
    "Delhi"                => ["New Delhi","Dwarka","Rohini","Janakpuri","Laxmi Nagar","Saket","Pitampura"],
    "Jammu & Kashmir"      => ["Srinagar","Jammu","Anantnag","Baramulla"],
    "Ladakh"               => ["Leh","Kargil"],
    "Chandigarh"           => ["Chandigarh"],
    "Puducherry"           => ["Puducherry","Karaikal","Mahe"],
    "Andaman & Nicobar"    => ["Port Blair"],
    "Dadra & Nagar Haveli" => ["Silvassa"],
    "Daman & Diu"          => ["Daman","Diu"],
    "Lakshadweep"          => ["Kavaratti"],
];
$stateCitiesJson = json_encode($stateCities, JSON_UNESCAPED_UNICODE);

// ── Helper: check if a date string is valid and non-zero ──
function isValidDate(?string $d): bool {
    if (!$d || $d === '' || $d === 'NULL') return false;
    if (preg_match('/^0+(-0+)*$/', str_replace(['-',':',' '], '', $d))) return false;
    $ts = strtotime($d);
    return ($ts !== false && $ts > 0);
}

// ── Excel serial date → Y-m-d (handles XLSX numeric date cells) ──
function excelDateToMysql($val): string {
    if ($val === '' || $val === null) return '0000-00-00';
    if (!is_numeric($val)) {
        $clean = str_replace('/', '-', trim($val));
        $ts = strtotime($clean);
        return ($ts && $ts > 0) ? date('Y-m-d', $ts) : '0000-00-00';
    }
    $serial = (float)$val;
    if ($serial <= 0) return '0000-00-00';
    if ($serial > 60) $serial--; // Excel leap-year bug fix
    $ts = mktime(0, 0, 0, 1, (int)($serial - 1), 1900);
    if (!$ts || $ts <= 0) return '0000-00-00';
    $year = (int)date('Y', $ts);
    if ($year < 1990 || $year > 2100) return '0000-00-00';
    return date('Y-m-d', $ts);
}

// Setup lead_activities table
$conn->query("CREATE TABLE IF NOT EXISTS lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    type ENUM('followup','call','note','meeting') DEFAULT 'followup',
    description TEXT,
    scheduled_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM lead_activities");
if ($col_res) { while ($c = $col_res->fetch_assoc()) $cols[] = $c['Field']; }
if (!in_array('type', $cols))           $conn->query("ALTER TABLE lead_activities ADD COLUMN type ENUM('followup','call','note','meeting') DEFAULT 'followup' AFTER domain_id");
if (!in_array('scheduled_date', $cols)) $conn->query("ALTER TABLE lead_activities ADD COLUMN scheduled_date DATETIME NULL AFTER description");
if (!in_array('description', $cols))    $conn->query("ALTER TABLE lead_activities ADD COLUMN description TEXT AFTER type");

// ── Auto-add columns if missing ──
$dbcols_check = [];
$cr_check = $conn->query("SHOW COLUMNS FROM domains");
if ($cr_check) while ($r = $cr_check->fetch_assoc()) $dbcols_check[] = $r['Field'];

if (!in_array('uploaded_at', $dbcols_check)) {
    $conn->query("ALTER TABLE domains ADD COLUMN uploaded_at TIMESTAMP NULL DEFAULT NULL");
    $conn->query("UPDATE domains SET uploaded_at = DATE_SUB(NOW(), INTERVAL (SELECT MAX(id) FROM (SELECT MAX(id) as id FROM domains) t) - id SECOND) WHERE uploaded_at IS NULL");
}
if (!in_array('create_date', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN create_date DATE DEFAULT NULL");
if (!in_array('registrant_company', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN registrant_company VARCHAR(255) DEFAULT NULL");
if (!in_array('registrant_address', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN registrant_address VARCHAR(500) DEFAULT NULL");
if (!in_array('registrant_country', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN registrant_country VARCHAR(100) DEFAULT NULL");
if (!in_array('registrant_zip', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN registrant_zip VARCHAR(20) DEFAULT NULL");
if (!in_array('domain_registrar_name', $dbcols_check))
    $conn->query("ALTER TABLE domains ADD COLUMN domain_registrar_name VARCHAR(255) DEFAULT NULL");

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($csrf, $token)) json_response(['ok' => false, 'msg' => 'CSRF error'], 403);
    $action = $_POST['action'] ?? '';

    // ── CSV / XLSX IMPORT ─────────────────────────────────────────
    if ($action === 'import_csv') {
        if (empty($_FILES['csv_file'])) json_response(['ok'=>false,'msg'=>'No file received.']);
        $uerr = $_FILES['csv_file']['error'];
        if ($uerr !== UPLOAD_ERR_OK) {
            $em = [1=>'File too large.',2=>'File too large.',3=>'Partial upload.',4=>'No file.',6=>'No tmp dir.',7=>'Cannot write.',8=>'Extension blocked.'];
            json_response(['ok'=>false,'msg'=>$em[$uerr]??'Upload error '.$uerr]);
        }
        $file     = $_FILES['csv_file']['tmp_name'];
        $fname    = strtolower($_FILES['csv_file']['name'] ?? '');
        $file_ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

        if (in_array($file_ext, ['xlsx','xls'])) {
            $csv_tmp = tempnam(sys_get_temp_dir(), 'xlsconv_');
            $zip = new ZipArchive();
            if ($zip->open($file) !== true)
                json_response(['ok'=>false,'msg'=>'Cannot open XLSX file. Try saving as CSV.']);

            // ── Shared strings ──
            $shared_strings = [];
            $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ss_xml) {
                $ss_doc = new SimpleXMLElement($ss_xml);
                foreach ($ss_doc->si as $si) {
                    $val = '';
                    if (isset($si->t)) $val = (string)$si->t;
                    elseif (isset($si->r)) foreach ($si->r as $r) if (isset($r->t)) $val .= (string)$r->t;
                    $shared_strings[] = $val;
                }
            }

            // ── Detect date-formatted cell style indexes ──
            $date_style_ids = [];
            $styles_xml = $zip->getFromName('xl/styles.xml');
            if ($styles_xml) {
                $sd = new SimpleXMLElement($styles_xml);
                $builtin_date = array_merge(range(14,17),[22],range(27,36),range(45,47),range(50,58));
                $custom_date_fmt_ids = [];
                if (isset($sd->numFmts)) {
                    foreach ($sd->numFmts->numFmt as $fmt) {
                        $fid  = (int)$fmt['numFmtId'];
                        $code = strtolower((string)$fmt['formatCode']);
                        if ($fid >= 164 && preg_match('/[ymd]/', $code) && strpos($code, '"') === false)
                            $custom_date_fmt_ids[] = $fid;
                    }
                }
                if (isset($sd->cellXfs)) {
                    $xi = 0;
                    foreach ($sd->cellXfs->xf as $xf) {
                        $fid = (int)$xf['numFmtId'];
                        if (in_array($fid, $builtin_date) || in_array($fid, $custom_date_fmt_ids))
                            $date_style_ids[] = $xi;
                        $xi++;
                    }
                }
            }

            // ── Parse sheet ──
            $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if (!$sheet_xml)
                json_response(['ok'=>false,'msg'=>'Could not read XLSX sheet. Try saving as CSV.']);

            $sheet      = new SimpleXMLElement($sheet_xml);
            $csv_handle = fopen($csv_tmp, 'w');
            foreach ($sheet->sheetData->row as $row_node) {
                $row_data = [];
                $prev_idx = 0;
                foreach ($row_node->c as $cell) {
                    preg_match('/([A-Z]+)/', (string)$cell['r'], $mc);
                    $col_idx = 0;
                    if (!empty($mc[1]))
                        foreach (str_split($mc[1]) as $ch)
                            $col_idx = $col_idx * 26 + (ord($ch) - 64);
                    $col_idx--;
                    while ($prev_idx < $col_idx) { $row_data[] = ''; $prev_idx++; }

                    $t = (string)($cell['t'] ?? '');
                    $s = isset($cell['s']) ? (int)$cell['s'] : -1;
                    $v = isset($cell->v) ? (string)$cell->v : '';

                    if ($t === 's') {
                        $cell_val = $shared_strings[(int)$v] ?? '';
                    } elseif ($t === 'inlineStr') {
                        $cell_val = isset($cell->is->t) ? (string)$cell->is->t : '';
                    } elseif ($t === 'd') {
                        $cell_val = $v ? date('Y-m-d', strtotime($v)) : '';
                    } elseif ($s >= 0 && in_array($s, $date_style_ids) && is_numeric($v)) {
                        $cell_val = excelDateToMysql($v);
                    } else {
                        $cell_val = $v;
                    }
                    $row_data[] = $cell_val;
                    $prev_idx++;
                }
                fputcsv($csv_handle, $row_data);
            }
            fclose($csv_handle);
            $file = $csv_tmp;
        }

        $handle = fopen($file, 'r');
        if (!$handle) json_response(['ok'=>false,'msg'=>'Cannot open file.']);
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); json_response(['ok'=>false,'msg'=>'Empty CSV.']); }
        $header = array_map(fn($h) => strtolower(trim(str_replace(['"',"'",'﻿'], '', $h))), $header);

        $colMap = [
            'domain_name'           => ['domain','domain_name','domain name'],
            'registrant_name'       => ['name','registrant_name','full name','contact name'],
            'registrant_email'      => ['email','registrant_email','email address'],
            'registrant_phone'      => ['phone','registrant_phone','mobile','phone number','registrant_contact'],
            'registrant_city'       => ['city','registrant_city'],
            'registrant_state'      => ['state','registrant_state','province'],
            'registrant_company'    => ['company','registrant_company','organization'],
            'registrant_address'    => ['address','registrant_address'],
            'registrant_country'    => ['country','registrant_country'],
            'registrant_zip'        => ['zip','registrant_zip','postal','postcode'],
            'domain_registrar_name' => ['registrar','domain_registrar_name','registrar name'],
            'expiry_date'           => ['expiry','expiry_date','expiration','expires','expiry date','expiration date'],
            'create_date'           => ['create_date','created_date','created','creation date','reg date','registration date','create date'],
        ];
        $idx = [];
        foreach ($colMap as $dbCol => $aliases)
            foreach ($header as $i => $h)
                if (in_array($h, $aliases) && !isset($idx[$i])) { $idx[$i] = $dbCol; break; }

        if (!in_array('domain_name', array_values($idx))) {
            fclose($handle);
            json_response(['ok'=>false,'msg'=>'Column "Domain" not found. Headers: '.implode(', ', $header)]);
        }

        $idx_res = $conn->query("SHOW INDEX FROM domains WHERE Key_name = 'uq_domain_name'");
        if (!($idx_res && $idx_res->num_rows > 0))
            $conn->query("ALTER TABLE domains ADD UNIQUE KEY uq_domain_name (domain_name)");

        $dbcols_imp = [];
        $cr_imp = $conn->query("SHOW COLUMNS FROM domains");
        if ($cr_imp) while ($r = $cr_imp->fetch_assoc()) $dbcols_imp[] = $r['Field'];

        $all_possible = [
            'domain_name','registrant_name','registrant_email','registrant_phone',
            'registrant_city','registrant_state','registrant_company','registrant_address',
            'registrant_country','registrant_zip','domain_registrar_name',
            'expiry_date','create_date'
        ];
        $ins_cols = array_values(array_filter($all_possible, fn($c) => in_array($c, $dbcols_imp)));

        $has_uploaded_at = in_array('uploaded_at', $dbcols_imp);
        $placeholders    = implode(',', array_fill(0, count($ins_cols), '?'));
        $ins_col_sql     = implode(',', $ins_cols);
        if ($has_uploaded_at) {
            $ins_col_sql  .= ',uploaded_at';
            $placeholders .= ',NOW()';
        }

        $upd_parts = [];
        foreach ($ins_cols as $c) {
            if ($c === 'domain_name') continue;
            if (in_array($c, ['expiry_date','create_date'])) {
                $upd_parts[] = "$c = IF(VALUES($c) > '0000-00-00' AND VALUES($c) IS NOT NULL, VALUES($c), $c)";
            } else {
                $upd_parts[] = "$c = IF(VALUES($c) IS NOT NULL AND VALUES($c) <> '', VALUES($c), $c)";
            }
        }
        if ($has_uploaded_at)
            $upd_parts[] = "uploaded_at = IF(uploaded_at IS NULL, NOW(), uploaded_at)";

        $sql  = "INSERT INTO domains ($ins_col_sql) VALUES ($placeholders) ON DUPLICATE KEY UPDATE ".implode(',', $upd_parts);
        $stmt = $conn->prepare($sql);
        if (!$stmt) { fclose($handle); json_response(['ok'=>false,'msg'=>'Prepare failed: '.$conn->error]); }

        $types_str = str_repeat('s', count($ins_cols));
        $inserted=0; $updated=0; $skipped=0; $errors=0; $last_err='';

        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row)) continue;

            $d = array_fill_keys($all_possible, null);
            $d['expiry_date'] = '0000-00-00';

            foreach ($idx as $ci => $dc)
                if (array_key_exists($dc, $d))
                    $d[$dc] = isset($row[$ci]) ? trim($row[$ci]) : null;

            if (!$d['domain_name']) { $skipped++; continue; }

            $e = $d['expiry_date'] ?? '';
            if ($e && $e !== '0000-00-00' && $e !== '') {
                if (is_numeric($e)) {
                    $d['expiry_date'] = excelDateToMysql($e);
                } else {
                    $ts = strtotime(str_replace('/', '-', $e));
                    $d['expiry_date'] = ($ts && $ts > 0) ? date('Y-m-d', $ts) : '0000-00-00';
                }
            } else {
                $d['expiry_date'] = '0000-00-00';
            }

            $cd = $d['create_date'] ?? '';
            if ($cd && $cd !== '' && $cd !== '0000-00-00') {
                if (is_numeric($cd)) {
                    $d['create_date'] = excelDateToMysql($cd);
                } else {
                    $ts2 = strtotime(str_replace('/', '-', $cd));
                    $d['create_date'] = ($ts2 && $ts2 > 0) ? date('Y-m-d', $ts2) : null;
                }
                if ($d['create_date']) {
                    $yr = (int)substr($d['create_date'], 0, 4);
                    if ($yr < 1990 || $yr > 2100) $d['create_date'] = null;
                }
            } else {
                $d['create_date'] = null;
            }

            $vals = [];
            foreach ($ins_cols as $c) {
                $vals[] = ($d[$c] === '') ? null : ($d[$c] ?? null);
            }

            $stmt->bind_param($types_str, ...$vals);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 2)      $updated++;
                elseif ($stmt->affected_rows === 1)  $inserted++;
                else                                 $skipped++;
            } else {
                $errors++;
                $last_err = $stmt->error;
            }
        }
        fclose($handle);
        $stmt->close();
        $msg = "✅ {$inserted} new, {$updated} updated, {$skipped} skipped";
        if ($errors) $msg .= " | ❌ {$errors} errors: {$last_err}";
        json_response(['ok'=>true,'msg'=>$msg,'inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors]);
    }

    // ── ADD LEAD MANUALLY ─────────────────────────────────────────
    if ($action === 'add_lead') {
        $domain = trim($_POST['domain_name'] ?? '');
        if (!$domain) json_response(['ok'=>false,'msg'=>'Domain name required.']);
        $name    = trim($_POST['registrant_name']       ?? '');
        $email   = trim($_POST['registrant_email']      ?? '');
        $phone   = trim($_POST['registrant_phone']      ?? '');
        $city    = trim($_POST['registrant_city']       ?? '');
        $state   = trim($_POST['registrant_state']      ?? '');
        $company = trim($_POST['registrant_company']    ?? '');
        $address = trim($_POST['registrant_address']    ?? '');
        $country = trim($_POST['registrant_country']    ?? '');
        $expiry  = trim($_POST['expiry_date']           ?? '') ?: '0000-00-00';
        $reg     = trim($_POST['domain_registrar_name'] ?? '');

        $dbcols=[];
        $cr=$conn->query("SHOW COLUMNS FROM domains");
        if ($cr) while($r=$cr->fetch_assoc()) $dbcols[]=$r['Field'];

        $ins_c = 'domain_name,registrant_name,registrant_email,registrant_phone,registrant_city,registrant_state';
        $ins_v = '?,?,?,?,?,?';
        $types = 'ssssss';
        $vals  = [$domain,$name,$email,$phone,$city,$state];

        if (in_array('registrant_company',$dbcols))    { $ins_c.=',registrant_company';    $ins_v.=',?'; $types.='s'; $vals[]=$company; }
        if (in_array('registrant_address',$dbcols))    { $ins_c.=',registrant_address';    $ins_v.=',?'; $types.='s'; $vals[]=$address; }
        if (in_array('registrant_country',$dbcols))    { $ins_c.=',registrant_country';    $ins_v.=',?'; $types.='s'; $vals[]=$country; }
        if (in_array('domain_registrar_name',$dbcols)) { $ins_c.=',domain_registrar_name'; $ins_v.=',?'; $types.='s'; $vals[]=$reg; }
        if (in_array('expiry_date',$dbcols))           { $ins_c.=',expiry_date';           $ins_v.=',?'; $types.='s'; $vals[]=$expiry; }
        if (in_array('uploaded_at',$dbcols))           { $ins_c.=',uploaded_at'; $ins_v.=',NOW()'; }
        elseif (in_array('create_date',$dbcols))       { $ins_c.=',create_date'; $ins_v.=',NOW()'; }

        $upd = "registrant_name=IF(VALUES(registrant_name)<>'',VALUES(registrant_name),registrant_name),"
             . "registrant_email=IF(VALUES(registrant_email)<>'',VALUES(registrant_email),registrant_email),"
             . "registrant_phone=IF(VALUES(registrant_phone)<>'',VALUES(registrant_phone),registrant_phone)";

        $sql = "INSERT INTO domains ($ins_c) VALUES ($ins_v) ON DUPLICATE KEY UPDATE $upd";
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_response(['ok'=>false,'msg'=>'Prepare error: '.$conn->error]);
        $stmt->bind_param($types, ...$vals);
        $ok  = $stmt->execute();
        $nid = $conn->insert_id;
        $err = $stmt->error;
        $stmt->close();
        json_response(['ok'=>$ok,'msg'=>$ok?'Lead saved!':'DB error: '.$err,'id'=>$nid]);
    }

    if ($action === 'add_activity') {
        $did  = intval_safe($_POST['domain_id']);
        $type = in_array($_POST['type']??'',['followup','call','note','meeting']) ? $_POST['type'] : 'followup';
        $desc = trim($_POST['description'] ?? '');
        $sched= !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        if ($did < 1 || $desc === '') json_response(['ok'=>false,'msg'=>'Missing fields']);
        $stmt = $conn->prepare("INSERT INTO lead_activities (domain_id,type,description,scheduled_date) VALUES (?,?,?,?)");
        $stmt->bind_param("isss",$did,$type,$desc,$sched);
        $ok = $stmt->execute();
        $stmt->close();
        json_response(['ok'=>$ok,'msg'=>$ok?'Saved':'DB error','id'=>$conn->insert_id]);
    }

    if ($action === 'delete_activity') {
        $id   = intval_safe($_POST['activity_id']);
        $stmt = $conn->prepare("DELETE FROM lead_activities WHERE id=?");
        $stmt->bind_param("i",$id);
        $ok = $stmt->execute();
        $stmt->close();
        json_response(['ok'=>$ok]);
    }

    if ($action === 'get_activities') {
        $did  = intval_safe($_POST['domain_id']);
        $stmt = $conn->prepare("SELECT * FROM lead_activities WHERE domain_id=? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i",$did);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        json_response(['ok'=>true,'activities'=>$rows]);
    }

    if ($action === 'debug_cols') {
        $cols=[]; $r=$conn->query("SHOW COLUMNS FROM domains");
        if ($r) while ($row=$r->fetch_assoc()) $cols[]=$row['Field'];
        $sample=[]; $r2=$conn->query("SELECT * FROM domains LIMIT 1");
        if ($r2) $sample=$r2->fetch_assoc()?:[];
        json_response(['ok'=>true,'columns'=>$cols,'sample'=>$sample]);
    }

    // ── DELETE SINGLE LEAD via AJAX ──
    if ($action === 'delete_lead') {
        $id = intval_safe($_POST['lead_id'] ?? 0);
        if ($id < 1) json_response(['ok'=>false,'msg'=>'Invalid ID']);
        $stmt = $conn->prepare("DELETE FROM domains WHERE id=?");
        $stmt->bind_param("i",$id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->query("DELETE FROM lead_activities WHERE domain_id=$id");
        json_response(['ok'=>$ok,'msg'=>$ok?'Deleted':'DB error']);
    }

    // ── DELETE ALL LEADS via AJAX ──
    if ($action === 'delete_all_leads') {
        $conn->query("DELETE FROM domains");
        $conn->query("DELETE FROM lead_activities");
        json_response(['ok'=>true,'msg'=>'All leads deleted']);
    }

    json_response(['ok'=>false,'msg'=>'Unknown action'], 400);
}

// ── Detect best timestamp column ──
$dbcols2=[]; $cr2=$conn->query("SHOW COLUMNS FROM domains");
if ($cr2) while ($r=$cr2->fetch_assoc()) $dbcols2[]=$r['Field'];
if (in_array('uploaded_at', $dbcols2))     $ts_col2 = 'uploaded_at';
elseif (in_array('create_date', $dbcols2)) $ts_col2 = 'create_date';
else                                        $ts_col2 = 'id';

// Filters
$search      = trim($_GET['search'] ?? '');
$state_filter= trim($_GET['state']  ?? '');
$date_filter = in_array($_GET['date'] ?? '', ['newest','oldest','expiring']) ? $_GET['date'] : 'newest';
$view        = (($_GET['view'] ?? 'card') === 'table') ? 'table' : 'card';
$page        = max(1, intval_safe($_GET['page'] ?? 1));
$per_page    = 24;

$order_sql = match($date_filter) {
    'oldest'   => " ORDER BY sort_ts ASC,  min_id ASC",
    'expiring' => " ORDER BY expiry_date_sort ASC",
    default    => " ORDER BY sort_ts DESC, min_id DESC",
};

$ts_expr = "COALESCE(
    MIN(CASE WHEN $ts_col2 IS NOT NULL AND $ts_col2 != '0000-00-00' AND $ts_col2 != '0000-00-00 00:00:00' THEN $ts_col2 ELSE NULL END),
    MIN(FROM_UNIXTIME(id))
)";

$sql="SELECT
  MIN(id) as id,
  MIN(id) as min_id,
  domain_name,
  MIN(registrant_name)  as registrant_name,
  MIN(registrant_email) as registrant_email,
  MIN(registrant_phone) as registrant_phone,
  MIN(registrant_city)  as registrant_city,
  MIN(registrant_state) as registrant_state,
  MIN(CASE WHEN expiry_date > '0000-00-00' THEN expiry_date ELSE NULL END) as expiry_date,
  MIN(CASE WHEN expiry_date > '0000-00-00' THEN expiry_date ELSE '9999-12-31' END) as expiry_date_sort,
  $ts_expr as sort_ts
  FROM domains WHERE 1=1";

$params=[]; $types="";
if ($search !== '') {
    $s = "%" . str_replace(['\\','%','_'],['\\\\','\\%','\\_'], $search) . "%";
    $sql .= " AND (domain_name LIKE ? OR registrant_name LIKE ? OR registrant_email LIKE ? OR registrant_phone LIKE ?)";
    array_push($params,$s,$s,$s,$s); $types .= "ssss";
}
if ($state_filter !== '') { $sql .= " AND registrant_state=?"; $params[]=$state_filter; $types.="s"; }
$sql .= " GROUP BY domain_name";

// Count
$csql = "SELECT COUNT(*) as cnt FROM ($sql) as sub";
$cst  = $conn->prepare($csql);
if ($cst) {
    if (!empty($params)) $cst->bind_param($types, ...$params);
    $cst->execute();
    $total_leads = $cst->get_result()->fetch_assoc()['cnt'] ?? 0;
    $cst->close();
} else $total_leads = 0;

$total_pages = max(1, (int)ceil($total_leads / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$sql   .= $order_sql . " LIMIT ? OFFSET ?";
$params[]=$per_page; $types.="i"; $params[]=$offset; $types.="i";
$stmt  = $conn->prepare($sql);
if (!$stmt) $leads = [];
else {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $leads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$states=[];
$sr=$conn->query("SELECT DISTINCT registrant_state FROM domains WHERE registrant_state!='' AND registrant_state NOT REGEXP '^[0-9]+$' AND LENGTH(registrant_state)>2 ORDER BY registrant_state");
if ($sr) { while($r=$sr->fetch_assoc()) $states[]=$r['registrant_state']; $sr->free(); }

$meetings=[];
$mr=$conn->query("SELECT la.*,d.domain_name FROM lead_activities la LEFT JOIN domains d ON la.domain_id=d.id WHERE la.type IN('meeting','call') AND la.scheduled_date>=NOW() ORDER BY la.scheduled_date ASC LIMIT 15");
if ($mr) { while($r=$mr->fetch_assoc()) $meetings[]=$r; }

$activity_counts=[];
$ac=$conn->query("SELECT domain_id,COUNT(*) as cnt FROM lead_activities GROUP BY domain_id");
if ($ac) { while($r=$ac->fetch_assoc()) $activity_counts[$r['domain_id']]=$r['cnt']; }

$total_activities = array_sum($activity_counts);
$total_states     = count($states);
$expiring_count   = 0;
$ec=$conn->query("SELECT COUNT(DISTINCT domain_name) as cnt FROM domains WHERE expiry_date BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 30 DAY) AND expiry_date>'0000-00-00'");
if ($ec) $expiring_count = $ec->fetch_assoc()['cnt'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#0f1117;--bg-2:#171b26;--bg-3:#1e2333;--border:rgba(255,255,255,0.07);--border-2:rgba(255,255,255,0.12);--text:#e8eaf0;--text-2:#8b90a0;--text-3:#555c70;--accent:#4f8ef7;--accent-2:#3d7de8;--accent-glow:rgba(79,142,247,0.2);--green:#34d399;--amber:#fbbf24;--red:#f87171;--purple:#a78bfa;--radius:12px;--radius-lg:16px;--shadow:0 4px 24px rgba(0,0,0,0.4);--shadow-lg:0 12px 48px rgba(0,0,0,0.6);--font:'DM Sans',sans-serif;--font-mono:'DM Mono',monospace;--sidebar-w:280px;--topbar-h:60px;--tr:0.18s cubic-bezier(0.4,0,0.2,1)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button{font-family:var(--font);cursor:pointer}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-2);border-radius:99px}
.topbar{position:fixed;top:0;left:0;right:0;z-index:200;height:var(--topbar-h);background:rgba(15,17,23,.9);border-bottom:1px solid var(--border);backdrop-filter:blur(20px);display:flex;align-items:center;padding:0 20px;gap:14px}
.topbar-logo{display:flex;align-items:center;gap:10px;width:var(--sidebar-w);min-width:var(--sidebar-w)}
.logo-icon{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--accent),#6366f1);display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 4px 12px var(--accent-glow);flex-shrink:0}
.logo-text{font-size:15px;font-weight:700;letter-spacing:-.3px}
.logo-text span{color:var(--accent)}
.topbar-center{flex:1;display:flex;align-items:center}
.search-wrap{flex:1;max-width:480px;position:relative}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:13px;pointer-events:none}
.topbar-search{width:100%;padding:9px 14px 9px 36px;background:var(--bg-2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-size:13.5px;font-family:var(--font);outline:none;transition:border var(--tr),box-shadow var(--tr)}
.topbar-search:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.topbar-search::placeholder{color:var(--text-3)}
.topbar-right{display:flex;align-items:center;gap:8px;margin-left:auto}
.icon-btn{width:36px;height:36px;border:1px solid var(--border);background:var(--bg-2);border-radius:9px;color:var(--text-2);display:flex;align-items:center;justify-content:center;font-size:14px;transition:all var(--tr);position:relative}
.icon-btn:hover{border-color:var(--border-2);color:var(--text);background:var(--bg-3)}
.badge{position:absolute;top:-5px;right:-5px;background:var(--red);color:#fff;font-size:9px;font-weight:700;width:16px;height:16px;border-radius:99px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg)}
.view-toggle{display:flex;gap:3px;background:var(--bg-2);border:1px solid var(--border);border-radius:9px;padding:3px}
.vt-btn{width:30px;height:28px;border:none;background:transparent;border-radius:6px;color:var(--text-3);font-size:13px;display:flex;align-items:center;justify-content:center;transition:all var(--tr)}
.vt-btn.active{background:var(--bg-3);color:var(--accent)}
.btn-view-reg{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid rgba(167,139,250,.35);background:rgba(167,139,250,.1);border-radius:9px;color:var(--purple);font-size:12.5px;font-weight:700;font-family:var(--font);transition:all var(--tr);white-space:nowrap;text-decoration:none;cursor:pointer}
.btn-view-reg:hover{background:rgba(167,139,250,.22);border-color:var(--purple);transform:translateY(-1px);box-shadow:0 4px 16px rgba(167,139,250,.2)}
.layout{display:flex;padding-top:var(--topbar-h);min-height:100vh}
.sidebar{width:var(--sidebar-w);min-width:var(--sidebar-w);background:var(--bg-2);border-right:1px solid var(--border);position:fixed;top:var(--topbar-h);left:0;bottom:0;overflow-y:auto;padding:16px 12px;display:flex;flex-direction:column;gap:20px;transition:transform var(--tr)}
.section-label{font-size:10px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:var(--text-3);padding:0 8px;margin-bottom:8px}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.stat-card{background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:12px;transition:border-color var(--tr)}
.stat-card:hover{border-color:var(--border-2)}
.stat-num{font-size:22px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums}
.stat-label{font-size:10.5px;color:var(--text-3);margin-top:3px;font-weight:500}
.stat-card.accent .stat-num{color:var(--accent)}
.stat-card.green .stat-num{color:var(--green)}
.stat-card.amber .stat-num{color:var(--amber)}
.nav-link{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;font-size:13px;font-weight:500;color:var(--text-2);transition:all var(--tr)}
.nav-link:hover{background:var(--bg-3);color:var(--text)}
.nav-link.active{background:var(--accent-glow);color:var(--accent)}
.nav-link i{width:16px;text-align:center;font-size:13px}
.meeting-list{display:flex;flex-direction:column;gap:6px}
.meeting-item{background:var(--bg-3);border:1px solid var(--border);border-left:3px solid var(--accent);border-radius:var(--radius);padding:10px;transition:all var(--tr)}
.meeting-item.is-call{border-left-color:var(--green)}
.mi-domain{font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:2px}
.mi-desc{font-size:11px;color:var(--text-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:5px}
.mi-time{font-size:10.5px;color:var(--accent);font-weight:500;display:flex;align-items:center;gap:4px}
.meeting-item.is-call .mi-time{color:var(--green)}
.no-meetings{text-align:center;padding:20px 8px;color:var(--text-3);font-size:12px}
.no-meetings i{display:block;font-size:24px;margin-bottom:6px;opacity:.4}
.main{flex:1;margin-left:var(--sidebar-w);padding:20px;min-width:0}
.toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:16px}
.filter-group{display:flex;gap:6px;align-items:center;flex-wrap:wrap;flex:1}
.filter-select{padding:8px 12px;background:var(--bg-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:var(--font);outline:none;transition:border var(--tr);appearance:none;cursor:pointer}
.filter-select:focus{border-color:var(--accent)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:var(--font);transition:all var(--tr);cursor:pointer;white-space:nowrap}
.btn-ghost{background:var(--bg-2);color:var(--text-2);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--bg-3);color:var(--text)}
.btn-danger{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.3)}
.btn-danger:hover{background:rgba(248,113,113,.2);border-color:var(--red);transform:translateY(-1px)}
.btn-import{background:rgba(52,211,153,.08);color:var(--green);border:1px solid rgba(52,211,153,.3)}
.btn-import:hover{background:rgba(52,211,153,.18);border-color:var(--green);transform:translateY(-1px)}
.btn-add-lead{background:rgba(79,142,247,.1);color:var(--accent);border:1px solid rgba(79,142,247,.3)}
.btn-add-lead:hover{background:rgba(79,142,247,.2);border-color:var(--accent);transform:translateY(-1px)}
.btn-bulk{background:var(--bg-2);color:var(--text-2);border:1px solid var(--border)}
.btn-bulk.active{background:rgba(79,142,247,.12);border-color:var(--accent);color:var(--accent)}
.btn-bulk:hover{background:var(--bg-3);color:var(--text)}
.meta-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.meta-count{font-size:13px;color:var(--text-3)}
.meta-count strong{color:var(--text);font-weight:600}
.pagination{display:flex;align-items:center;gap:4px}
.pg-btn{padding:5px 10px;border:1px solid var(--border);background:var(--bg-2);border-radius:7px;color:var(--text-2);font-size:12px;font-weight:500;font-family:var(--font);transition:all var(--tr);cursor:pointer}
.pg-btn:hover{border-color:var(--border-2);color:var(--text)}
.pg-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}
.pg-btn:disabled{opacity:.3;cursor:default}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px}
.lead-card{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;transition:all var(--tr);display:flex;flex-direction:column;position:relative}
.lead-card:hover{border-color:var(--border-2);transform:translateY(-2px);box-shadow:var(--shadow)}
.card-accent-line{height:2px;background:linear-gradient(90deg,var(--accent),#6366f1);opacity:.7;transition:all .3s}
.card-header{padding:14px 14px 10px;display:flex;justify-content:space-between;align-items:flex-start}
.card-domain{font-size:13px;font-weight:700;color:var(--text);word-break:break-all;line-height:1.3;margin-bottom:3px;font-family:var(--font-mono)}
.card-expiry{font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:4px;margin-top:2px}
.card-expiry.soon{color:var(--amber)}
.card-expiry.expired{color:var(--red)}
.card-create-date{font-size:10.5px;color:var(--text-3);display:flex;align-items:center;gap:4px;margin-top:3px}
.act-pill{background:var(--bg-3);border:1px solid var(--border);font-size:10px;font-weight:700;color:var(--text-2);padding:3px 8px;border-radius:99px;white-space:nowrap;flex-shrink:0;margin-left:8px}
.act-pill.has-acts{border-color:var(--accent);color:var(--accent);background:var(--accent-glow)}
.card-body{padding:4px 14px 12px;flex:1}
.card-row{display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12.5px}
.card-row-icon{width:14px;color:var(--text-3);font-size:11px;flex-shrink:0;text-align:center}
.card-row-val{color:var(--text-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.card-row-val a{color:var(--accent)}
.card-row-val.phone a{color:var(--green)}
.card-tags{padding:2px 14px 10px;display:flex;gap:5px;flex-wrap:wrap}
.tag{font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:99px;border:1px solid var(--border);color:var(--text-3);background:var(--bg-3)}
.tag.state{border-color:rgba(79,142,247,.3);color:var(--accent);background:var(--accent-glow)}
.card-footer{padding:8px 10px;border-top:1px solid var(--border);display:flex;gap:5px}
.cta{flex:1;padding:7px 4px;border:1px solid var(--border);background:transparent;border-radius:8px;font-size:11px;font-weight:600;font-family:var(--font);color:var(--text-2);display:flex;align-items:center;justify-content:center;gap:4px;transition:all var(--tr);text-decoration:none}
.cta:hover{transform:translateY(-1px)}
.cta-call:hover{background:rgba(52,211,153,.12);border-color:var(--green);color:var(--green)}
.cta-note:hover{background:rgba(251,191,36,.1);border-color:var(--amber);color:var(--amber)}
.cta-meet:hover{background:rgba(167,139,250,.1);border-color:var(--purple);color:var(--purple)}
.cta-more:hover{background:rgba(79,142,247,.1);border-color:var(--accent);color:var(--accent)}
.cta-del{flex:0;padding:7px 9px;border:1px solid rgba(248,113,113,.25);background:transparent;border-radius:8px;font-size:12px;font-weight:600;font-family:var(--font);color:var(--red);display:flex;align-items:center;justify-content:center;transition:all var(--tr);cursor:pointer}
.cta-del:hover{background:rgba(248,113,113,.12);border-color:var(--red);transform:translateY(-1px)}
.phone-num-wrap{display:inline-block;position:relative}
.phone-blurred{filter:blur(5px);user-select:none;transition:filter .25s;border-radius:3px;pointer-events:none}
.phone-num-wrap.revealed .phone-blurred{filter:none;pointer-events:auto}
.tbl-phone-blur{filter:blur(4px);user-select:none;transition:filter .2s;display:inline-block;pointer-events:none}
.tbl-phone-wrap.revealed .tbl-phone-blur{filter:none;pointer-events:auto}
.phone-popup-wrap{position:relative;flex:1}
.phone-popup{display:none;position:absolute;bottom:calc(100% + 8px);left:125%;transform:translateX(-50%);background:var(--bg-3);border:1px solid var(--green);border-radius:10px;padding:10px 14px;white-space:nowrap;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.5);min-width:180px;text-align:center}
.phone-popup::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:var(--green)}
.phone-popup.show{display:block;animation:popIn .18s ease}
@keyframes popIn{from{opacity:0;transform:translateX(-50%) translateY(6px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
.pp-num{font-size:15px;font-weight:700;color:var(--green);letter-spacing:.5px;margin-bottom:6px;font-family:var(--font-mono)}
.pp-actions{display:flex;gap:6px;justify-content:center}
.pp-btn{flex:1;padding:5px 8px;border-radius:7px;border:1px solid var(--border);background:var(--bg-2);color:var(--text-2);font-size:11px;font-weight:600;font-family:var(--font);cursor:pointer;transition:all var(--tr);display:flex;align-items:center;justify-content:center;gap:4px;text-decoration:none}
.pp-btn.copy-btn:hover{border-color:var(--amber);color:var(--amber)}
.pp-btn.dial-btn:hover{border-color:var(--green);color:var(--green)}
.pp-btn.copied{border-color:var(--green);color:var(--green)}
.tbl-phone-popup{display:none;position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:var(--bg-3);border:1px solid var(--green);border-radius:10px;padding:10px 14px;white-space:nowrap;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.5);min-width:180px;text-align:center}
.tbl-phone-popup::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:var(--green)}
.tbl-phone-popup.show{display:block;animation:popIn .18s ease}
.table-wrap{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
thead{background:var(--bg-3)}
thead th{padding:12px 14px;text-align:left;color:var(--text-3);font-weight:600;font-size:11px;letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background var(--tr)}
tbody tr:hover{background:var(--bg-3)}
tbody tr:last-child{border-bottom:none}
tbody td{padding:11px 14px;vertical-align:middle}
.tbl-domain{font-weight:600;color:var(--text);font-family:var(--font-mono);font-size:12.5px;white-space:nowrap}
.tbl-state{display:inline-block;background:var(--accent-glow);color:var(--accent);border:1px solid rgba(79,142,247,.3);padding:2px 8px;border-radius:99px;font-size:10.5px;font-weight:600}
.tbl-dash{color:var(--text-3)}
.tbl-actions{display:flex;gap:5px}
.tbl-btn{padding:5px 9px;border:1px solid var(--border);background:transparent;border-radius:7px;color:var(--text-3);font-size:13px;transition:all var(--tr);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.tbl-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-glow)}
.tbl-btn.del:hover{border-color:var(--red);color:var(--red);background:rgba(248,113,113,.08)}
.tbl-cb{cursor:pointer;accent-color:var(--accent);width:14px;height:14px}
tbody tr.selected{background:rgba(79,142,247,.06)!important}
tbody tr.selected td:first-child{border-left:2px solid var(--accent)}
.card-checkbox-wrap{display:none;position:absolute;top:10px;left:10px;z-index:10}
.bulk-mode .card-checkbox-wrap{display:block}
.card-checkbox{width:18px;height:18px;cursor:pointer;accent-color:var(--accent)}
.bulk-mode .lead-card{cursor:pointer}
.lead-card.selected{border-color:var(--accent)!important;box-shadow:0 0 0 2px var(--accent-glow),var(--shadow)}
.lead-card.selected .card-accent-line{background:linear-gradient(90deg,var(--accent),#34d399);opacity:1}
.bulk-bar{display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:400;background:var(--bg-3);border:1px solid var(--border-2);border-radius:14px;padding:10px 16px;gap:10px;align-items:center;box-shadow:0 8px 32px rgba(0,0,0,.6);backdrop-filter:blur(12px);min-width:340px}
.bulk-bar.show{display:flex;animation:barIn .22s ease}
@keyframes barIn{from{opacity:0;transform:translateX(-50%) translateY(16px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
.bulk-count{font-size:13px;font-weight:700;background:var(--accent-glow);border:1px solid rgba(79,142,247,.3);padding:4px 12px;border-radius:99px;white-space:nowrap}
.bulk-count span{color:var(--accent)}
.bulk-actions{display:flex;gap:6px;flex-wrap:wrap}
.bulk-btn{padding:7px 14px;border:1px solid var(--border);background:var(--bg-2);border-radius:8px;color:var(--text-2);font-size:12.5px;font-weight:600;font-family:var(--font);cursor:pointer;transition:all var(--tr);display:flex;align-items:center;gap:5px;white-space:nowrap}
.bulk-btn.export:hover{border-color:var(--green);color:var(--green);background:rgba(52,211,153,.08)}
.bulk-btn.clear:hover{border-color:var(--red);color:var(--red);background:rgba(248,113,113,.08)}
.bulk-sep{width:1px;height:24px;background:var(--border);flex-shrink:0}
.select-all-wrap{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-2);cursor:pointer;white-space:nowrap}
.select-all-wrap input{cursor:pointer;accent-color:var(--accent);width:14px;height:14px}
.overlay,.import-overlay,.add-lead-overlay,.confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(8px);padding:20px}
.overlay.open,.import-overlay.open,.add-lead-overlay.open,.confirm-overlay.open{display:flex}
.modal,.import-modal,.add-lead-modal{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);width:100%;box-shadow:var(--shadow-lg);animation:modalIn .22s ease;overflow:hidden}
.modal{max-width:480px}
.import-modal{max-width:460px}
.add-lead-modal{max-width:520px}
.confirm-modal{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);width:100%;max-width:380px;box-shadow:var(--shadow-lg);animation:modalIn .22s ease;overflow:hidden;padding:28px 24px;text-align:center}
.confirm-modal .confirm-icon{font-size:36px;margin-bottom:12px}
.confirm-modal h3{font-size:16px;font-weight:700;margin-bottom:8px}
.confirm-modal p{font-size:13px;color:var(--text-3);margin-bottom:20px;line-height:1.5}
.confirm-modal .confirm-btns{display:flex;gap:8px;justify-content:center}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head,.import-head,.al-head{padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.modal-title{font-size:16px;font-weight:700;flex:1}
.modal-close{width:30px;height:30px;border:1px solid var(--border);background:transparent;border-radius:7px;color:var(--text-3);font-size:16px;display:flex;align-items:center;justify-content:center;transition:all var(--tr)}
.modal-close:hover{border-color:var(--border-2);color:var(--text)}
.modal-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.modal-icon.call{background:rgba(52,211,153,.15);color:var(--green)}
.modal-icon.note{background:rgba(251,191,36,.15);color:var(--amber)}
.modal-icon.meeting{background:rgba(167,139,250,.15);color:var(--purple)}
.modal-icon.followup{background:var(--accent-glow);color:var(--accent)}
.import-icon{width:36px;height:36px;border-radius:9px;background:rgba(52,211,153,.15);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.al-icon{width:36px;height:36px;border-radius:9px;background:var(--accent-glow);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:16px}
.type-tabs{display:flex;gap:6px;padding:14px 20px 0}
.tt{flex:1;padding:8px 4px;border:1px solid var(--border);background:transparent;border-radius:8px;cursor:pointer;font-size:11.5px;font-weight:600;font-family:var(--font);color:var(--text-3);text-align:center;transition:all var(--tr);display:flex;flex-direction:column;align-items:center;gap:3px}
.tt i{font-size:14px}
.tt:hover{border-color:var(--border-2);color:var(--text-2)}
.tt[data-type="call"].selected{background:rgba(52,211,153,.1);border-color:var(--green);color:var(--green)}
.tt[data-type="note"].selected{background:rgba(251,191,36,.1);border-color:var(--amber);color:var(--amber)}
.tt[data-type="meeting"].selected{background:rgba(167,139,250,.1);border-color:var(--purple);color:var(--purple)}
.tt[data-type="followup"].selected{background:var(--accent-glow);border-color:var(--accent);color:var(--accent)}
.modal-body,.import-body{padding:16px 20px 4px}
.al-body{padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
.al-body .form-group{margin-bottom:0}
.al-body .form-group.full{grid-column:1/-1}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text-3);margin-bottom:6px;letter-spacing:.3px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 13px;background:var(--bg-3);border:1px solid var(--border);border-radius:9px;color:var(--text);font-size:13.5px;font-family:var(--font);outline:none;transition:border var(--tr),box-shadow var(--tr)}
.form-group select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23555c70' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
.form-group select option{background:var(--bg-3);color:var(--text)}
.form-group select:disabled{opacity:.45;cursor:default}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.form-group textarea{resize:vertical;min-height:80px;line-height:1.5}
.form-group input[readonly]{opacity:.5;cursor:default}
.modal-foot,.import-foot,.al-foot{padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid var(--border)}
.btn-save,.btn-al-save{background:var(--accent);color:#fff;font-weight:700}
.btn-save:hover,.btn-al-save:hover{background:var(--accent-2)}
.btn-al-save:disabled{opacity:.5;cursor:default}
.act-list{margin-top:4px;margin-bottom:14px;display:flex;flex-direction:column;gap:5px}
.act-item{background:var(--bg-3);border:1px solid var(--border);border-radius:9px;padding:10px 12px;display:flex;gap:10px;align-items:flex-start}
.act-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:1px}
.act-icon.call{background:rgba(52,211,153,.15);color:var(--green)}
.act-icon.note{background:rgba(251,191,36,.15);color:var(--amber)}
.act-icon.meeting{background:rgba(167,139,250,.15);color:var(--purple)}
.act-icon.followup{background:var(--accent-glow);color:var(--accent)}
.act-content{flex:1;min-width:0}
.act-desc{font-size:12.5px;color:var(--text-2);line-height:1.4}
.act-meta{font-size:10.5px;color:var(--text-3);margin-top:3px;display:flex;gap:8px}
.act-del{background:transparent;border:none;color:var(--text-3);font-size:12px;cursor:pointer;padding:3px 5px;border-radius:5px;transition:all var(--tr);flex-shrink:0}
.act-del:hover{color:var(--red)}
.acts-loading{text-align:center;padding:12px;color:var(--text-3);font-size:13px}
.modal-divider{border:none;border-top:1px solid var(--border);margin:4px 0 12px}
.drop-zone{border:2px dashed rgba(52,211,153,.3);border-radius:12px;padding:32px 20px;text-align:center;cursor:pointer;transition:all var(--tr);background:rgba(52,211,153,.03);position:relative}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--green);background:rgba(52,211,153,.08)}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.drop-icon{font-size:32px;margin-bottom:10px;opacity:.6}
.drop-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px}
.drop-sub{font-size:12px;color:var(--text-3)}
.drop-fname{font-size:12px;color:var(--green);margin-top:8px;font-weight:600;display:none}
.col-info{margin-top:14px;background:var(--bg-3);border:1px solid var(--border);border-radius:9px;padding:12px 14px}
.col-info-title{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px}
.col-tags{display:flex;flex-wrap:wrap;gap:5px}
.col-tag{font-size:11px;padding:3px 8px;border-radius:99px;background:var(--accent-glow);border:1px solid rgba(79,142,247,.2);color:var(--accent);font-family:var(--font-mono)}
.import-progress{display:none;margin-top:14px}
.progress-bar-wrap{height:6px;background:var(--bg-3);border-radius:99px;overflow:hidden;margin-bottom:8px}
.progress-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--accent));border-radius:99px;width:0%;transition:width .3s}
.progress-label{font-size:12px;color:var(--text-3);text-align:center}
.import-result{display:none;margin-top:12px;padding:12px 14px;border-radius:9px;font-size:13px;font-weight:500;text-align:center}
.import-result.success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:var(--green)}
.import-result.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:var(--red)}
.btn-do-import{background:var(--green);color:#000;font-weight:700}
.btn-do-import:hover{background:#2ec48a}
.btn-do-import:disabled{opacity:.5;cursor:default;transform:none}
.toast-wrap{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;background:var(--bg-3);border:1px solid var(--border);box-shadow:var(--shadow);min-width:240px;max-width:340px;animation:toastIn .25s ease;font-size:13.5px;font-weight:500}
.toast.success{border-color:rgba(52,211,153,.3);color:var(--green)}
.toast.error{border-color:rgba(248,113,113,.3);color:var(--red)}
.toast.info{border-color:rgba(79,142,247,.3);color:var(--accent)}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{to{opacity:0;transform:translateX(20px)}}
.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;margin:auto}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{text-align:center;padding:80px 20px;color:var(--text-3)}
.empty-state i{font-size:40px;display:block;margin-bottom:12px;opacity:.3}
.empty-state p{font-size:14px}
.sidebar-toggle{display:none}
/* ── State/City select highlight ── */
.al-body .form-group select:not([disabled]):hover{border-color:var(--border-2)}
.city-loading{color:var(--text-3);font-style:italic}
@media(max-width:1024px){:root{--sidebar-w:240px}}
@media(max-width:768px){.sidebar{transform:translateX(-100%);z-index:300}.sidebar.open{transform:translateX(0)}.main{margin-left:0}.topbar-logo{width:auto;min-width:auto}.sidebar-toggle{display:flex}.cards-grid{grid-template-columns:1fr}.bulk-bar{min-width:calc(100vw - 32px)}.btn-view-reg span{display:none}}
@media(max-width:500px){.search-wrap{display:none}.main{padding:12px}}
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-logo">
    <button class="icon-btn sidebar-toggle" id="sidebarToggle"><i class="fa fa-bars"></i></button>
    <div class="logo-icon">📋</div>
    <div class="logo-text">Lead<span>Pro</span></div>
  </div>
  <div class="topbar-center">
    <div class="search-wrap">
      <i class="fa fa-magnifying-glass"></i>
      <input class="topbar-search" type="text" id="searchInput" placeholder="Search domain, name, email, phone…" value="<?= h($search) ?>" autocomplete="off" spellcheck="false">
    </div>
  </div>
  <div class="topbar-right">
    <a href="view_register.php" class="btn-view-reg" target="_blank" title="Open Registration View">
      <i class="fa fa-users-viewfinder"></i>
      <span>View Register</span>
    </a>
    <div class="view-toggle">
      <button class="vt-btn <?= $view==='card'?'active':'' ?>" onclick="switchView('card')" title="Card"><i class="fa fa-grip"></i></button>
      <button class="vt-btn <?= $view==='table'?'active':'' ?>" onclick="switchView('table')" title="Table"><i class="fa fa-table-list"></i></button>
    </div>
    <button class="icon-btn" id="bellBtn">
      <i class="fa fa-bell"></i>
      <?php if(count($meetings)>0): ?><span class="badge"><?= count($meetings) ?></span><?php endif; ?>
    </button>
  </div>
</header>

<div class="layout">
<aside class="sidebar" id="sidebar">
  <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px">
    <a href="admin_login1.php?logout=1" style="display:flex;align-items:center;gap:6px;padding:7px 12px;border:1px solid rgba(248,113,113,.3);border-radius:8px;background:rgba(248,113,113,.08);color:var(--red);font-size:12px;font-weight:600;text-decoration:none;transition:all .18s;white-space:nowrap" onmouseover="this.style.background='rgba(248,113,113,.18)'" onmouseout="this.style.background='rgba(248,113,113,.08)'">
      <i class="fa fa-right-from-bracket"></i> Logout
    </a>
  </div>
  <div>
    <div class="section-label">Overview</div>
    <div class="stat-grid">
      <div class="stat-card accent"><div class="stat-num"><?= number_format($total_leads) ?></div><div class="stat-label">Total Leads</div></div>
      <div class="stat-card"><div class="stat-num"><?= $total_states ?></div><div class="stat-label">States</div></div>
      <div class="stat-card green"><div class="stat-num"><?= $total_activities ?></div><div class="stat-label">Activities</div></div>
      <div class="stat-card amber"><div class="stat-num"><?= $expiring_count ?></div><div class="stat-label">Expiring 30d</div></div>
    </div>
  </div>
  <div>
    <div class="section-label">Quick Filters</div>
    <a href="?view=<?= $view ?>&date=newest" class="nav-link <?= $date_filter==='newest'&&!$state_filter&&!$search?'active':'' ?>"><i class="fa fa-clock-rotate-left"></i> Newest Leads</a>
    <a href="?view=<?= $view ?>&date=expiring" class="nav-link <?= $date_filter==='expiring'&&!$state_filter&&!$search?'active':'' ?>"><i class="fa fa-hourglass-half"></i> Expiring Soon</a>
    <a href="?view=<?= $view ?>&date=oldest" class="nav-link"><i class="fa fa-arrow-up-1-9"></i> Oldest First</a>
  </div>
  <?php if(!empty($states)): ?>
  <div>
    <div class="section-label">States</div>
    <?php foreach(array_slice($states,0,15) as $st): ?>
      <a href="?view=<?= $view ?>&state=<?= urlencode($st) ?>" class="nav-link <?= $state_filter===$st?'active':'' ?>"><i class="fa fa-location-dot"></i> <?= h($st) ?></a>
    <?php endforeach; ?>
    <?php if(count($states)>15): ?><div style="font-size:11px;color:var(--text-3);padding:4px 8px">+<?= count($states)-15 ?> more…</div><?php endif; ?>
  </div>
  <?php endif; ?>
  <div>
    <div class="section-label">📅 Upcoming</div>
    <?php if(empty($meetings)): ?>
      <div class="no-meetings"><i class="fa fa-calendar-check"></i>No upcoming meetings</div>
    <?php else: ?>
      <div class="meeting-list">
        <?php foreach($meetings as $m): ?>
          <div class="meeting-item <?= $m['type']==='call'?'is-call':'' ?>">
            <div class="mi-domain"><?= $m['type']==='call'?'📞':'📅' ?> <?= h($m['domain_name']??'Domain #'.$m['domain_id']) ?></div>
            <div class="mi-desc"><?= h($m['description']) ?></div>
            <div class="mi-time"><i class="fa fa-clock"></i><?= date('d M · h:i A',strtotime($m['scheduled_date'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</aside>

<main class="main" id="mainContent">
  <form class="toolbar" method="GET" action="lead.php" id="filterForm">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <div class="filter-group">
      <select class="filter-select" name="state" onchange="this.form.submit()">
        <option value="">All States</option>
        <?php foreach($states as $st): ?><option value="<?= h($st) ?>" <?= $state_filter===$st?'selected':'' ?>><?= h($st) ?></option><?php endforeach; ?>
      </select>
      <select class="filter-select" name="date" onchange="this.form.submit()">
        <option value="newest"   <?= $date_filter==='newest'?'selected':'' ?>>Newest First</option>
        <option value="oldest"   <?= $date_filter==='oldest'?'selected':'' ?>>Oldest First</option>
        <option value="expiring" <?= $date_filter==='expiring'?'selected':'' ?>>Expiring Soon</option>
      </select>
      <input type="hidden" name="search" value="<?= h($search) ?>">
    </div>
    <a href="admin_follow.php" class="btn btn-ghost"><i class="fa fa-rotate-right"></i> Follow Up</a>
    <a href="lead.php?view=<?= $view ?>" class="btn btn-ghost"><i class="fa fa-rotate-right"></i> Reset</a>
    <button type="button" class="btn btn-import"   onclick="openImportModal()"><i class="fa fa-file-import"></i> Import CSV</button>
    <button type="button" class="btn btn-add-lead" onclick="openAddLeadModal()"><i class="fa fa-plus"></i> Add Lead</button>
    <button type="button" class="btn btn-bulk" id="bulkToggleBtn" onclick="toggleBulkMode()"><i class="fa fa-check-square"></i> Bulk Select</button>
    <button type="button" class="btn btn-danger" onclick="confirmDeleteAll()"><i class="fa fa-trash"></i> Delete All</button>
  </form>

  <div class="meta-bar">
    <div class="meta-count">Showing <strong><?= count($leads) ?></strong> of <strong><?= number_format($total_leads) ?></strong> leads<?php if($search): ?> · "<strong><?= h($search) ?></strong>"<?php endif; ?><?php if($state_filter): ?> · <strong><?= h($state_filter) ?></strong><?php endif; ?></div>
    <?php if($total_pages>1): ?>
    <div class="pagination">
      <button class="pg-btn" onclick="goPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>>‹</button>
      <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?><button class="pg-btn <?= $p===$page?'active':'' ?>" onclick="goPage(<?= $p ?>)"><?= $p ?></button><?php endfor; ?>
      <button class="pg-btn" onclick="goPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>>›</button>
    </div>
    <?php endif; ?>
  </div>

<?php if($view==='card'): ?>
  <?php if(empty($leads)): ?>
    <div class="empty-state"><i class="fa fa-inbox"></i><p>No leads found.</p></div>
  <?php else: ?>
  <div class="cards-grid" id="cardsGrid">
  <?php foreach($leads as $lead):
    $acts  = $activity_counts[$lead['id']] ?? 0;
    $er    = $lead['expiry_date'] ?? '';
    $expiry= null; $expClass='';
    if ($er && isValidDate($er)) {
        $expiry = strtotime($er);
        if ($expiry > 0) {
            $diff = ($expiry - time()) / 86400;
            $expClass = $diff < 0 ? 'expired' : ($diff < 30 ? 'soon' : '');
        } else { $expiry = null; }
    }
    $sort_ts  = $lead['sort_ts'] ?? '';
    $cdDisplay= isValidDate($sort_ts) ? date('d M Y', strtotime($sort_ts)) : '';
    $phone    = trim($lead['registrant_phone'] ?? $lead['phone'] ?? $lead['registrant_contact'] ?? '');
  ?>
    <div class="lead-card"
         data-lead-id="<?= (int)$lead['id'] ?>"
         data-domain="<?= h($lead['domain_name']) ?>"
         data-name="<?= h($lead['registrant_name']??'') ?>"
         data-email="<?= h($lead['registrant_email']??'') ?>"
         data-phone="<?= h($phone) ?>"
         data-city="<?= h($lead['registrant_city']??'') ?>"
         data-state="<?= h($lead['registrant_state']??'') ?>"
         data-expiry="<?= h($er) ?>"
         data-added="<?= h($cdDisplay) ?>"
         onclick="handleCardClick(event,this)">
      <div class="card-checkbox-wrap"><input type="checkbox" class="card-checkbox lead-cb" data-id="<?= (int)$lead['id'] ?>" onclick="event.stopPropagation();toggleCardSelect(this)"></div>
      <div class="card-accent-line"></div>
      <div class="card-header">
        <div>
          <div class="card-domain"><?= h($lead['domain_name']) ?></div>
          <div class="card-expiry <?= $expClass ?>">
            <i class="fa fa-circle-dot" style="font-size:7px"></i>
            Expiry: <?= ($expiry && $expiry > 0) ? date('d M Y', $expiry) : ($er && $er !== '0000-00-00' ? h($er) : 'N/A') ?>
          </div>
          <div class="card-create-date">
            <i class="fa fa-calendar-plus" style="font-size:7px"></i>
            Added: <?= $cdDisplay ?: 'N/A' ?>
          </div>
        </div>
        <span class="act-pill <?= $acts>0?'has-acts':'' ?>"><?= $acts ?> act</span>
      </div>
      <div class="card-body">
        <div class="card-row"><span class="card-row-icon"><i class="fa fa-user"></i></span><span class="card-row-val"><?= h($lead['registrant_name'] ?: '—') ?></span></div>
        <div class="card-row"><span class="card-row-icon"><i class="fa fa-envelope"></i></span><span class="card-row-val"><?php if($lead['registrant_email']): ?><a href="mailto:<?= h($lead['registrant_email']) ?>" onclick="event.stopPropagation()"><?= h($lead['registrant_email']) ?></a><?php else: ?>—<?php endif; ?></span></div>
        <div class="card-row">
          <span class="card-row-icon"><i class="fa fa-phone"></i></span>
          <span class="card-row-val phone">
            <?php if($phone): ?>
              <span class="phone-num-wrap" id="pnw-<?= (int)$lead['id'] ?>">
                <span class="phone-blurred"><?= h($phone) ?></span>
              </span>
            <?php else: ?>—<?php endif; ?>
          </span>
        </div>
        <div class="card-row"><span class="card-row-icon"><i class="fa fa-location-dot"></i></span><span class="card-row-val"><?php $loc=array_filter([$lead['registrant_city']??'',$lead['registrant_state']??'']);echo $loc?h(implode(', ',$loc)):'—'; ?></span></div>
      </div>
      <?php $ss=$lead['registrant_state']??''; if($ss&&!is_numeric($ss)&&strlen($ss)>2): ?><div class="card-tags"><span class="tag state"><?= h($ss) ?></span></div><?php endif; ?>
      <div class="card-footer" onclick="event.stopPropagation()">
        <?php if($phone): ?>
          <div class="phone-popup-wrap">
            <button class="cta cta-call" onclick="togglePhonePopup(this,'<?= h(addslashes($phone)) ?>',<?= (int)$lead['id'] ?>)"><i class="fa fa-phone"></i> Call</button>
            <div class="phone-popup">
              <div class="pp-actions">
                <button class="pp-btn copy-btn" onclick="copyPhone('<?= h(addslashes($phone)) ?>',this)"><i class="fa fa-copy"></i> Copy</button>
                <a class="pp-btn dial-btn" href="tel:<?= h($phone) ?>"><i class="fa fa-phone"></i> Dial</a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <button class="cta cta-call" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','call')"><i class="fa fa-phone"></i> Call</button>
        <?php endif; ?>
        <button class="cta cta-note" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','note')"><i class="fa fa-note-sticky"></i> Note</button>
        <button class="cta cta-meet" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','meeting')"><i class="fa fa-calendar"></i> Meet</button>
        <button class="cta cta-more" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','followup')"><i class="fa fa-plus"></i></button>
        <button class="cta-del" onclick="confirmDeleteLead(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>')" title="Delete Lead"><i class="fa fa-trash"></i></button>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr>
      <th style="width:36px"><input type="checkbox" class="tbl-cb" id="selectAllCb" onchange="toggleSelectAll(this.checked)"></th>
      <th>#</th><th>Domain</th><th>Name</th><th>Email</th><th>Phone</th>
      <th>City</th><th>State</th><th>Create Date</th><th>Expiry</th><th>Acts</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php if(empty($leads)): ?>
      <tr><td colspan="12" style="text-align:center;padding:50px;color:var(--text-3)">No leads found.</td></tr>
    <?php else: ?>
    <?php foreach($leads as $i=>$lead):
      $acts  = $activity_counts[$lead['id']] ?? 0;
      $phone = trim($lead['registrant_phone'] ?? $lead['phone'] ?? $lead['registrant_contact'] ?? '');
      $er2   = $lead['expiry_date'] ?? '';
      $expiry2Str = isValidDate($er2) ? date('d M Y', strtotime($er2)) : ($er2 && $er2!=='0000-00-00' ? h($er2) : 'N/A');
      $sort_ts2   = $lead['sort_ts'] ?? '';
      $cd2Display = isValidDate($sort_ts2) ? date('d M Y', strtotime($sort_ts2)) : 'N/A';
    ?>
    <tr data-lead-id="<?= (int)$lead['id'] ?>"
        data-domain="<?= h($lead['domain_name']) ?>"
        data-name="<?= h($lead['registrant_name']??'') ?>"
        data-email="<?= h($lead['registrant_email']??'') ?>"
        data-phone="<?= h($phone) ?>"
        data-city="<?= h($lead['registrant_city']??'') ?>"
        data-state="<?= h($lead['registrant_state']??'') ?>"
        data-expiry="<?= h($er2) ?>"
        data-added="<?= h($cd2Display !== 'N/A' ? $cd2Display : '') ?>">
      <td><input type="checkbox" class="tbl-cb lead-cb" data-id="<?= (int)$lead['id'] ?>" onchange="onRowCbChange(this)"></td>
      <td style="color:var(--text-3);font-size:12px"><?= $offset+$i+1 ?></td>
      <td class="tbl-domain"><?= h($lead['domain_name']) ?></td>
      <td style="color:var(--text-2)"><?= $lead['registrant_name'] ? h($lead['registrant_name']) : '<span class="tbl-dash">—</span>' ?></td>
      <td><?php if($lead['registrant_email']): ?><a href="mailto:<?= h($lead['registrant_email']) ?>" style="color:var(--accent)"><?= h($lead['registrant_email']) ?></a><?php else: ?><span class="tbl-dash">—</span><?php endif; ?></td>
      <td>
        <?php if($phone): ?>
          <div class="tbl-phone-wrap" id="tpw-<?= (int)$lead['id'] ?>" style="position:relative;display:inline-block">
            <span class="tbl-phone-blur"><?= h($phone) ?></span>
            <div class="tbl-phone-popup">
              <div class="pp-actions">
                <button class="pp-btn copy-btn" onclick="copyPhone('<?= h(addslashes($phone)) ?>',this)"><i class="fa fa-copy"></i> Copy</button>
                <a class="pp-btn dial-btn" href="tel:<?= h($phone) ?>"><i class="fa fa-phone"></i> Dial</a>
              </div>
            </div>
          </div>
        <?php else: ?><span class="tbl-dash">—</span><?php endif; ?>
      </td>
      <td style="color:var(--text-2)"><?= h($lead['registrant_city'] ?: '—') ?></td>
      <td><?= $lead['registrant_state'] ? '<span class="tbl-state">'.h($lead['registrant_state']).'</span>' : '<span class="tbl-dash">—</span>' ?></td>
      <td style="color:var(--text-3);font-size:12px"><?= h($cd2Display) ?></td>
      <td style="color:var(--text-3);font-size:12px"><?= h($expiry2Str) ?></td>
      <td><?= $acts>0 ? '<span class="act-pill has-acts">'.$acts.'</span>' : '<span class="tbl-dash">—</span>' ?></td>
      <td><div class="tbl-actions">
        <?php if($phone): ?>
          <button class="tbl-btn" style="border-color:rgba(52,211,153,.35);color:var(--green)" onclick="toggleTblPhone(this,'<?= h(addslashes($phone)) ?>',<?= (int)$lead['id'] ?>)">📞</button>
        <?php else: ?>
          <button class="tbl-btn" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','call')">📞</button>
        <?php endif; ?>
        <button class="tbl-btn" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','note')">📝</button>
        <button class="tbl-btn" onclick="openModal(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>','meeting')">📅</button>
        <button class="tbl-btn del" onclick="confirmDeleteLead(<?= (int)$lead['id'] ?>,'<?= h(addslashes($lead['domain_name'])) ?>')" title="Delete"><i class="fa fa-trash"></i></button>
      </div></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table></div>
<?php endif; ?>

  <?php if($total_pages>1): ?>
  <div style="display:flex;justify-content:center;margin-top:20px">
    <div class="pagination">
      <button class="pg-btn" onclick="goPage(1)" <?= $page<=1?'disabled':'' ?>>«</button>
      <button class="pg-btn" onclick="goPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>>‹</button>
      <?php for($p=max(1,$page-3);$p<=min($total_pages,$page+3);$p++): ?><button class="pg-btn <?= $p===$page?'active':'' ?>" onclick="goPage(<?= $p ?>)"><?= $p ?></button><?php endfor; ?>
      <button class="pg-btn" onclick="goPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>>›</button>
      <button class="pg-btn" onclick="goPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?>>»</button>
    </div>
  </div>
  <?php endif; ?>
</main>
</div>

<!-- IMPORT MODAL -->
<div class="import-overlay" id="importOverlay">
  <div class="import-modal">
    <div class="import-head"><div class="import-icon"><i class="fa fa-file-import"></i></div><div class="modal-title">Import CSV / XLSX to Database</div><button class="modal-close" onclick="closeImportModal()">×</button></div>
    <div class="import-body">
      <div class="drop-zone" id="dropZone">
        <input type="file" id="csvFileInput" accept="*/*" onchange="onFileSelected(this)">
        <div class="drop-icon">📂</div>
        <div class="drop-title">Click or drag CSV / XLSX file here</div>
        <div class="drop-sub">Domain, Create Date, Expiry Date, Name, Email, Phone, City, State…</div>
        <div class="drop-fname" id="dropFname"></div>
      </div>
      <div class="col-info">
        <div class="col-info-title">Expected Columns</div>
        <div class="col-tags">
          <span class="col-tag">domain_name</span>
          <span class="col-tag">create_date</span>
          <span class="col-tag">expiry_date</span>
          <span class="col-tag">registrant_name</span>
          <span class="col-tag">registrant_email</span>
          <span class="col-tag">registrant_phone</span>
          <span class="col-tag">registrant_city</span>
          <span class="col-tag">registrant_state</span>
          <span class="col-tag">registrant_company</span>
          <span class="col-tag">domain_registrar_name</span>
        </div>
        <div style="font-size:11px;color:var(--text-3);margin-top:8px">✅ XLSX date cells auto-converted • Duplicates updated, not duplicated</div>
      </div>
      <div class="import-progress" id="importProgress"><div class="progress-bar-wrap"><div class="progress-bar" id="progressBar"></div></div><div class="progress-label" id="progressLabel">Uploading…</div></div>
      <div class="import-result" id="importResult"></div>
    </div>
    <div class="import-foot">
      <button type="button" class="btn btn-ghost" onclick="closeImportModal()">Cancel</button>
      <button type="button" class="btn btn-do-import" id="doImportBtn" onclick="doImport()" disabled><i class="fa fa-upload"></i> Import to Database</button>
    </div>
  </div>
</div>

<!-- ADD LEAD MODAL -->
<div class="add-lead-overlay" id="addLeadOverlay">
  <div class="add-lead-modal">
    <div class="al-head"><div class="al-icon"><i class="fa fa-plus"></i></div><div class="modal-title">Add New Lead</div><button class="modal-close" onclick="closeAddLeadModal()">×</button></div>
    <div class="al-body">
      <div class="form-group full"><label>Domain Name *</label><input type="text" id="al_domain" placeholder="example.com" autocomplete="off"></div>
      <div class="form-group"><label>Name</label><input type="text" id="al_name" placeholder="Full name"></div>
      <div class="form-group"><label>Company</label><input type="text" id="al_company" placeholder="Company"></div>
      <div class="form-group"><label>Email</label><input type="email" id="al_email" placeholder="email@example.com"></div>
      <div class="form-group"><label>Phone</label><input type="text" id="al_phone" placeholder="Phone number"></div>

      <!-- ── State Dropdown ── -->
      <div class="form-group">
        <label>State</label>
        <select id="al_state" onchange="onAlStateChange()">
          <option value="">— Select State —</option>
          <?php foreach(array_keys($stateCities) as $st): ?>
          <option value="<?= h($st) ?>"><?= h($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ── City Dropdown (cascades from State) ── -->
      <div class="form-group">
        <label>City</label>
        <select id="al_city" disabled>
          <option value="">— Select State First —</option>
        </select>
      </div>

      <div class="form-group"><label>Country</label><input type="text" id="al_country" value="India"></div>
      <div class="form-group"><label>Expiry Date</label><input type="date" id="al_expiry"></div>
      <div class="form-group full"><label>Address</label><input type="text" id="al_address" placeholder="Full address"></div>
      <div class="form-group full"><label>Registrar</label><input type="text" id="al_registrar" placeholder="e.g. GoDaddy, NameCheap"></div>
    </div>
    <div class="al-foot">
      <div id="al_result" style="flex:1;font-size:13px;display:none"></div>
      <button type="button" class="btn btn-ghost" onclick="closeAddLeadModal()">Cancel</button>
      <button type="button" class="btn btn-al-save" id="alSaveBtn" onclick="saveNewLead()"><i class="fa fa-check"></i> Save Lead</button>
    </div>
  </div>
</div>

<!-- CONFIRM DELETE MODAL -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-modal">
    <div class="confirm-icon" id="confirmIcon">🗑️</div>
    <h3 id="confirmTitle">Delete Lead?</h3>
    <p id="confirmMsg">Are you sure you want to delete this lead? This action cannot be undone.</p>
    <div class="confirm-btns">
      <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
      <button class="btn btn-danger" id="confirmOkBtn" onclick="doConfirmedDelete()"><i class="fa fa-trash"></i> Delete</button>
    </div>
  </div>
</div>

<!-- BULK BAR -->
<div class="bulk-bar" id="bulkBar">
  <div class="bulk-count"><span id="bulkCountNum">0</span> selected</div>
  <div class="bulk-sep"></div>
  <label class="select-all-wrap"><input type="checkbox" id="bulkSelectAllCb" onchange="toggleSelectAll(this.checked)"> Select All</label>
  <div class="bulk-sep"></div>
  <div class="bulk-actions">
    <button class="bulk-btn export" onclick="exportSelected('csv')"><i class="fa fa-file-csv"></i> Export CSV</button>
    <button class="bulk-btn export" onclick="exportSelected('excel')"><i class="fa fa-file-excel"></i> Export Excel</button>
    <button class="bulk-btn clear"  onclick="clearSelection()"><i class="fa fa-xmark"></i> Clear</button>
  </div>
</div>

<!-- ACTIVITY MODAL -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head"><div class="modal-icon call" id="modalIcon"><i class="fa fa-phone"></i></div><div class="modal-title" id="modalTitle">Log a Call</div><button class="modal-close" onclick="closeModal()">×</button></div>
    <div class="type-tabs">
      <button type="button" class="tt" data-type="call"     onclick="setType('call')"><i class="fa fa-phone"></i> Call</button>
      <button type="button" class="tt" data-type="note"     onclick="setType('note')"><i class="fa fa-note-sticky"></i> Note</button>
      <button type="button" class="tt" data-type="meeting"  onclick="setType('meeting')"><i class="fa fa-calendar"></i> Meeting</button>
      <button type="button" class="tt" data-type="followup" onclick="setType('followup')"><i class="fa fa-rotate-right"></i> Follow-up</button>
    </div>
    <div class="modal-body">
      <div class="form-group"><label>Domain</label><input type="text" id="modalDomainName" readonly></div>
      <div class="form-group"><label id="descLabel">Notes *</label><textarea id="modalDesc" placeholder="Enter details…" required></textarea></div>
      <div class="form-group"><label id="schedLabel">Schedule Date & Time</label><input type="datetime-local" id="modalSched"></div>
      <hr class="modal-divider">
      <div class="section-label" style="margin-bottom:8px">Past Activities</div>
      <div class="act-list" id="actList"><div class="acts-loading"><div class="spinner"></div></div></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button type="button" class="btn btn-save btn-primary" id="saveBtn" onclick="saveActivity()"><i class="fa fa-check"></i> Save</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const CSRF = '<?= $csrf ?>';

// ── State → City data from PHP ──
const stateCitiesData = <?= $stateCitiesJson ?>;

let activeDomainId = null, activeType = 'call';

// ── CONFIRM DELETE STATE ──
let _confirmMode = null;
let _confirmLeadId = null;
let _confirmDomainName = '';

function toast(msg, type='info', dur=3200) {
  const ic = {success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info'};
  const el = Object.assign(document.createElement('div'), {className:`toast ${type}`, innerHTML:`<i class="fa ${ic[type]}"></i><span>${msg}</span>`});
  document.getElementById('toastWrap').appendChild(el);
  setTimeout(() => { el.style.animation='toastOut .25s ease forwards'; el.addEventListener('animationend',()=>el.remove()); }, dur);
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtDate(s) { return new Date(s.replace(' ','T')).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}); }

async function api(data) {
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  Object.entries(data).forEach(([k,v]) => fd.append(k,v));
  const r = await fetch('lead.php', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd});
  const txt = await r.text();
  try { return JSON.parse(txt); }
  catch(e) { return {ok:false, msg:'Server error: '+txt.replace(/<[^>]+>/g,'').trim().slice(0,200)}; }
}

function switchView(v) { const u=new URL(location.href); u.searchParams.set('view',v); u.searchParams.delete('page'); location.href=u; }
function goPage(p)     { const u=new URL(location.href); u.searchParams.set('page',p); location.href=u; }

let st;
document.getElementById('searchInput').addEventListener('input', function() {
  clearTimeout(st);
  st = setTimeout(() => { const u=new URL(location.href); u.searchParams.set('search',this.value.trim()); u.searchParams.delete('page'); location.href=u; }, 500);
});
document.getElementById('searchInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); clearTimeout(st); document.getElementById('searchInput').dispatchEvent(new Event('input')); }
});

const sb = document.getElementById('sidebarToggle'), si = document.getElementById('sidebar');
if (sb) {
  sb.addEventListener('click', () => si.classList.toggle('open'));
  document.addEventListener('click', e => { if (!si.contains(e.target) && !sb.contains(e.target)) si.classList.remove('open'); });
}
document.getElementById('bellBtn').addEventListener('click', () => si.classList.toggle('open'));
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeModal(); closeImportModal(); closeAddLeadModal(); closeConfirm(); } });

// ── CONFIRM DELETE MODAL ──
function confirmDeleteLead(id, domain) {
  _confirmMode = 'single';
  _confirmLeadId = id;
  _confirmDomainName = domain;
  document.getElementById('confirmIcon').textContent = '🗑️';
  document.getElementById('confirmTitle').textContent = 'Delete Lead?';
  document.getElementById('confirmMsg').innerHTML = 'Are you sure you want to delete <strong>' + escHtml(domain) + '</strong>?<br>This action cannot be undone.';
  document.getElementById('confirmOkBtn').innerHTML = '<i class="fa fa-trash"></i> Delete';
  document.getElementById('confirmOverlay').classList.add('open');
}

function confirmDeleteAll() {
  _confirmMode = 'all';
  document.getElementById('confirmIcon').textContent = '⚠️';
  document.getElementById('confirmTitle').textContent = 'Delete ALL Leads?';
  document.getElementById('confirmMsg').textContent = 'This will permanently delete ALL leads and their activities. This action cannot be undone!';
  document.getElementById('confirmOkBtn').innerHTML = '<i class="fa fa-trash"></i> Delete All';
  document.getElementById('confirmOverlay').classList.add('open');
}

function closeConfirm() {
  document.getElementById('confirmOverlay').classList.remove('open');
  _confirmMode = null; _confirmLeadId = null;
}

async function doConfirmedDelete() {
  const btn = document.getElementById('confirmOkBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;margin:0 auto"></div>';

  if (_confirmMode === 'single') {
    const res = await api({action:'delete_lead', lead_id: _confirmLeadId});
    if (res.ok) {
      toast('Lead deleted ✓', 'success');
      const card = document.querySelector('.lead-card[data-lead-id="'+_confirmLeadId+'"]');
      if (card) { card.style.opacity='0'; card.style.transform='scale(.95)'; card.style.transition='all .25s'; setTimeout(()=>card.remove(),250); }
      const row  = document.querySelector('tr[data-lead-id="'+_confirmLeadId+'"]');
      if (row)  { row.style.opacity='0'; setTimeout(()=>row.remove(),250); }
      const mc = document.querySelector('.meta-count strong');
      if (mc) { const v=parseInt(mc.textContent.replace(/,/g,'')); mc.textContent=Math.max(0,v-1).toLocaleString(); }
      closeConfirm();
    } else {
      toast(res.msg||'Delete failed!', 'error');
      btn.disabled=false;
      btn.innerHTML='<i class="fa fa-trash"></i> Delete';
    }
  } else if (_confirmMode === 'all') {
    const res = await api({action:'delete_all_leads'});
    if (res.ok) {
      toast('All leads deleted ✓', 'success');
      closeConfirm();
      setTimeout(()=>location.reload(), 800);
    } else {
      toast(res.msg||'Failed!', 'error');
      btn.disabled=false;
      btn.innerHTML='<i class="fa fa-trash"></i> Delete All';
    }
  }
}

document.getElementById('confirmOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('confirmOverlay')) closeConfirm();
});

// ── PHONE POPUP ──
function togglePhonePopup(btn, phone, leadId) {
  document.querySelectorAll('.phone-popup.show').forEach(p => { if(p!==btn.nextElementSibling) p.classList.remove('show'); });
  document.querySelectorAll('.phone-num-wrap.revealed').forEach(w => { if(w.id!=='pnw-'+leadId) w.classList.remove('revealed'); });
  const popup = btn.nextElementSibling;
  const wrap  = document.getElementById('pnw-'+leadId);
  const show  = popup.classList.toggle('show');
  if (wrap) { show ? wrap.classList.add('revealed') : wrap.classList.remove('revealed'); }
  event.stopPropagation();
}
function toggleTblPhone(btn, phone, leadId) {
  const wrap = document.getElementById('tpw-'+leadId);
  if (!wrap) return;
  const popup = wrap.querySelector('.tbl-phone-popup');
  document.querySelectorAll('.tbl-phone-popup.show').forEach(p => { if(p!==popup) p.classList.remove('show'); });
  document.querySelectorAll('.tbl-phone-wrap.revealed').forEach(w => { if(w.id!=='tpw-'+leadId) w.classList.remove('revealed'); });
  const show = popup.classList.toggle('show');
  show ? wrap.classList.add('revealed') : wrap.classList.remove('revealed');
  event.stopPropagation();
}
function copyPhone(phone, btn) {
  navigator.clipboard.writeText(phone).catch(() => {
    const t = document.createElement('textarea'); t.value=phone; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t);
  });
  btn.innerHTML = '<i class="fa fa-check"></i> Copied!'; btn.classList.add('copied');
  setTimeout(() => { btn.innerHTML='<i class="fa fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
  event.stopPropagation();
}
document.addEventListener('click', () => {
  document.querySelectorAll('.phone-popup.show,.tbl-phone-popup.show').forEach(p => p.classList.remove('show'));
  document.querySelectorAll('.phone-num-wrap.revealed,.tbl-phone-wrap.revealed').forEach(w => w.classList.remove('revealed'));
});

// ── IMPORT ──
let selFile = null;
function openImportModal()  { document.getElementById('importOverlay').classList.add('open'); resetImp(); }
function closeImportModal() { document.getElementById('importOverlay').classList.remove('open'); }
function resetImp() {
  selFile = null;
  document.getElementById('csvFileInput').value = '';
  const fn = document.getElementById('dropFname'); fn.style.display='none'; fn.textContent='';
  document.getElementById('doImportBtn').disabled = true;
  document.getElementById('importProgress').style.display = 'none';
  document.getElementById('importResult').style.display   = 'none';
  document.getElementById('progressBar').style.width = '0%';
}
function onFileSelected(inp) {
  const f = inp.files[0]; if (!f) return;
  selFile = f;
  const fn = document.getElementById('dropFname');
  fn.textContent = '📄 '+f.name+' ('+(f.size/1024).toFixed(1)+' KB)'; fn.style.display='block';
  document.getElementById('doImportBtn').disabled = false;
  document.getElementById('importResult').style.display = 'none';
}
const dz = document.getElementById('dropZone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.classList.remove('dragover');
  const f = e.dataTransfer.files[0];
  if (f) { document.getElementById('csvFileInput').files = e.dataTransfer.files; onFileSelected(document.getElementById('csvFileInput')); }
});

async function doImport() {
  if (!selFile) { toast('Please select a file.', 'error'); return; }
  const btn  = document.getElementById('doImportBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;margin:0 4px 0 0;display:inline-block;vertical-align:middle"></div> Importing…';
  const prog = document.getElementById('importProgress'), bar = document.getElementById('progressBar'),
        lbl  = document.getElementById('progressLabel'),  res = document.getElementById('importResult');
  prog.style.display = 'block'; res.style.display = 'none';
  let pct = 0;
  const tk = setInterval(() => { pct = Math.min(pct + Math.random()*15, 85); bar.style.width=pct+'%'; lbl.textContent='Uploading… '+Math.round(pct)+'%'; }, 200);
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF); fd.append('action','import_csv'); fd.append('csv_file', selFile);
    const r   = await fetch('lead.php', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd});
    const txt = await r.text();
    clearInterval(tk); bar.style.width='100%'; lbl.textContent='Done!';
    let data;
    try { data = JSON.parse(txt); }
    catch(e) {
      res.style.display='block'; res.className='import-result error';
      res.innerHTML = '<i class="fa fa-circle-xmark"></i> Server error: '+escHtml(txt.replace(/<[^>]+>/g,'').trim().slice(0,300));
      btn.disabled=false; btn.innerHTML='<i class="fa fa-upload"></i> Import to Database'; return;
    }
    res.style.display = 'block';
    if (data.ok) {
      res.className='import-result success'; res.innerHTML='<i class="fa fa-circle-check"></i> '+data.msg;
      toast(data.msg, 'success', 4000);
      setTimeout(() => location.reload(), 1500);
    } else {
      res.className='import-result error'; res.innerHTML='<i class="fa fa-circle-xmark"></i> '+escHtml(data.msg);
      toast(data.msg, 'error');
      btn.disabled=false; btn.innerHTML='<i class="fa fa-upload"></i> Import to Database';
    }
  } catch(e) {
    clearInterval(tk);
    res.style.display='block'; res.className='import-result error'; res.innerHTML='<i class="fa fa-circle-xmark"></i> '+escHtml(e.message);
    btn.disabled=false; btn.innerHTML='<i class="fa fa-upload"></i> Import to Database';
  }
}

// ── ADD LEAD: State → City cascade ──
function onAlStateChange() {
  const state   = document.getElementById('al_state').value;
  const citySel = document.getElementById('al_city');

  citySel.innerHTML = '';

  if (!state || !stateCitiesData[state]) {
    citySel.innerHTML = '<option value="">— Select State First —</option>';
    citySel.disabled  = true;
    return;
  }

  // Populate cities for the selected state
  const placeholder = document.createElement('option');
  placeholder.value = ''; placeholder.textContent = '— Select City —';
  citySel.appendChild(placeholder);

  stateCitiesData[state].forEach(city => {
    const o = document.createElement('option');
    o.value = city; o.textContent = city;
    citySel.appendChild(o);
  });

  citySel.disabled = false;
  citySel.focus();
}

// ── ADD LEAD MODAL ──
function openAddLeadModal() {
  document.getElementById('addLeadOverlay').classList.add('open');

  // Reset text inputs
  ['al_domain','al_name','al_company','al_email','al_phone','al_expiry','al_address','al_registrar'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('al_country').value = 'India';

  // Reset state dropdown
  document.getElementById('al_state').value = '';

  // Reset city dropdown
  const citySel = document.getElementById('al_city');
  citySel.innerHTML = '<option value="">— Select State First —</option>';
  citySel.disabled  = true;

  document.getElementById('al_result').style.display = 'none';
  const b = document.getElementById('alSaveBtn'); b.disabled=false; b.innerHTML='<i class="fa fa-check"></i> Save Lead';
  setTimeout(() => document.getElementById('al_domain').focus(), 100);
}

function closeAddLeadModal() { document.getElementById('addLeadOverlay').classList.remove('open'); }
document.getElementById('addLeadOverlay').addEventListener('click', e => { if(e.target===document.getElementById('addLeadOverlay')) closeAddLeadModal(); });

async function saveNewLead() {
  const domain = document.getElementById('al_domain').value.trim();
  const btn = document.getElementById('alSaveBtn');
  btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;margin:0 auto"></div>';
  const re  = document.getElementById('al_result');
  const res = await api({
    action:'add_lead', domain_name:domain,
    registrant_name:    document.getElementById('al_name').value.trim(),
    registrant_company: document.getElementById('al_company').value.trim(),
    registrant_email:   document.getElementById('al_email').value.trim(),
    registrant_phone:   document.getElementById('al_phone').value.trim(),
    registrant_city:    document.getElementById('al_city').value.trim(),
    registrant_state:   document.getElementById('al_state').value.trim(),
    registrant_country: document.getElementById('al_country').value.trim(),
    registrant_address: document.getElementById('al_address').value.trim(),
    domain_registrar_name: document.getElementById('al_registrar').value.trim(),
    expiry_date: document.getElementById('al_expiry').value || '0000-00-00'
  });
  if (res.ok) {
    toast('Lead saved! ✅', 'success');
    re.style.display='block'; re.style.color='var(--green)'; re.innerHTML='<i class="fa fa-circle-check"></i> Saved!';
    setTimeout(() => { closeAddLeadModal(); location.reload(); }, 1200);
  } else {
    toast(res.msg||'Failed.', 'error');
    re.style.display='block'; re.style.color='var(--red)'; re.innerHTML='<i class="fa fa-circle-xmark"></i> '+escHtml(res.msg||'Error');
    btn.disabled=false; btn.innerHTML='<i class="fa fa-check"></i> Save Lead';
  }
}

// ── BULK SELECT ──
let bulkMode=false, selIds=new Set(), selData=new Map();
function toggleBulkMode() {
  bulkMode = !bulkMode;
  document.getElementById('bulkToggleBtn').classList.toggle('active', bulkMode);
  const g = document.getElementById('cardsGrid'); if(g) g.classList.toggle('bulk-mode', bulkMode);
  if (!bulkMode) clearSelection(); else toast('Select leads to export', 'info', 2000);
}
function handleCardClick(e, card) {
  if (!bulkMode) return;
  const cb = card.querySelector('.card-checkbox');
  if (cb && e.target !== cb) { cb.checked = !cb.checked; toggleCardSelect(cb); }
}
function exData(el) {
  return {domain:el.dataset.domain||'', name:el.dataset.name||'', email:el.dataset.email||'',
          phone:el.dataset.phone||'', city:el.dataset.city||'', state:el.dataset.state||'',
          expiry:el.dataset.expiry||'', added:el.dataset.added||''};
}
function toggleCardSelect(cb) {
  const id=parseInt(cb.dataset.id), card=cb.closest('.lead-card');
  if (cb.checked) { selIds.add(id); if(card){card.classList.add('selected');selData.set(id,exData(card));} }
  else            { selIds.delete(id); if(card) card.classList.remove('selected'); selData.delete(id); }
  updBar();
}
function onRowCbChange(cb) {
  const id=parseInt(cb.dataset.id), row=cb.closest('tr');
  if (cb.checked) { selIds.add(id); if(row){row.classList.add('selected');selData.set(id,exData(row));} }
  else            { selIds.delete(id); if(row) row.classList.remove('selected'); selData.delete(id); const h=document.getElementById('selectAllCb'); if(h) h.checked=false; }
  updBar();
}
function toggleSelectAll(checked) {
  ['selectAllCb','bulkSelectAllCb'].forEach(id => { const c=document.getElementById(id); if(c) c.checked=checked; });
  document.querySelectorAll('.lead-card .lead-cb').forEach(cb => { cb.checked=checked; toggleCardSelect(cb); });
  document.querySelectorAll('tbody .lead-cb').forEach(cb => { cb.checked=checked; onRowCbChange(cb); });
}
function clearSelection() {
  selIds.clear(); selData.clear();
  document.querySelectorAll('.lead-cb').forEach(cb => cb.checked=false);
  document.querySelectorAll('.lead-card.selected').forEach(c => c.classList.remove('selected'));
  document.querySelectorAll('tbody tr.selected').forEach(r => r.classList.remove('selected'));
  ['selectAllCb','bulkSelectAllCb'].forEach(id => { const c=document.getElementById(id); if(c) c.checked=false; });
  updBar();
}
function updBar() { const n=selIds.size; document.getElementById('bulkCountNum').textContent=n; document.getElementById('bulkBar').classList.toggle('show', n>0); }

function exportSelected(fmt) {
  const rows = [];
  document.querySelectorAll('.lead-cb:checked').forEach(cb => { const el=cb.closest('.lead-card')||cb.closest('tr'); if(el) rows.push(exData(el)); });
  if (!rows.length) { toast('No leads selected!','error'); return; }
  const hd = ['Domain','Name','Email','Phone','City','State','Expiry','Added Date'];
  const co = ['domain','name','email','phone','city','state','expiry','added'];
  if (fmt === 'csv') {
    let csv = hd.join(',') + '\n';
    rows.forEach(r => { csv += co.map(c => '"'+(r[c]||'').replace(/"/g,'""')+'"').join(',') + '\n'; });
    dlFile(csv, `leads_export_${today()}.csv`, 'text/csv');
    toast(rows.length+' leads exported ✓', 'success');
  } else {
    let html = '<html><head><meta charset="UTF-8"></head><body><table><tr>'+hd.map(h=>`<th>${h}</th>`).join('')+'</tr>';
    rows.forEach(r => { html += '<tr>'+co.map(c=>`<td>${escHtml(r[c]||'')}</td>`).join('')+'</tr>'; });
    html += '</table></body></html>';
    dlFile(html, `leads_export_${today()}.xls`, 'application/vnd.ms-excel');
    toast(rows.length+' leads exported ✓', 'success');
  }
  clearSelection(); if (bulkMode) toggleBulkMode();
}
function dlFile(content, filename, mime) {
  const a = Object.assign(document.createElement('a'), {href:URL.createObjectURL(new Blob([content],{type:mime})), download:filename});
  a.click(); URL.revokeObjectURL(a.href);
}
function today() { return new Date().toISOString().slice(0,10); }

// ── ACTIVITY MODAL ──
function openModal(did, dn, type='call') {
  activeDomainId = did;
  document.getElementById('modalDomainName').value = dn;
  document.getElementById('overlay').classList.add('open');
  document.getElementById('modalDesc').value  = '';
  document.getElementById('modalSched').value = '';
  setType(type); loadActivities(did);
}
function closeModal() { document.getElementById('overlay').classList.remove('open'); activeDomainId=null; }
document.getElementById('overlay').addEventListener('click', e => { if(e.target===document.getElementById('overlay')) closeModal(); });

const TC = {
  call:    {title:'📞 Log a Call',      icon:'fa-phone',        cls:'call',    desc:'Call Notes *',  sched:'Call Back Date',     ph:'What was discussed…', req:false},
  note:    {title:'📝 Add a Note',      icon:'fa-note-sticky',  cls:'note',    desc:'Note *',        sched:'Reminder',           ph:'Your note…',          req:false},
  meeting: {title:'📅 Schedule Meeting',icon:'fa-calendar',     cls:'meeting', desc:'Agenda *',      sched:'Meeting Date & Time *',ph:'Meeting about…',     req:true},
  followup:{title:'🔁 Follow-Up',       icon:'fa-rotate-right', cls:'followup',desc:'Details *',     sched:'Follow-Up Date',     ph:'Follow-up details…',  req:false}
};
function setType(type) {
  activeType = type;
  const c = TC[type], mi = document.getElementById('modalIcon');
  document.getElementById('modalTitle').textContent  = c.title;
  document.getElementById('descLabel').textContent   = c.desc;
  document.getElementById('schedLabel').textContent  = c.sched;
  document.getElementById('modalDesc').placeholder   = c.ph;
  document.getElementById('modalSched').required     = c.req;
  mi.className = `modal-icon ${c.cls}`; mi.innerHTML = `<i class="fa ${c.icon}"></i>`;
  document.querySelectorAll('.tt').forEach(t => t.classList.toggle('selected', t.dataset.type===type));
  const cl = {call:'var(--green)',note:'var(--amber)',meeting:'var(--purple)',followup:'var(--accent)'};
  document.getElementById('saveBtn').style.background = cl[type];
}
async function saveActivity() {
  const desc  = document.getElementById('modalDesc').value.trim();
  const sched = document.getElementById('modalSched').value;
  if (!desc) { toast('Enter description.','error'); return; }
  if (activeType==='meeting' && !sched) { toast('Set meeting date.','error'); return; }
  const btn = document.getElementById('saveBtn');
  btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;margin:0 auto"></div>';
  const res = await api({action:'add_activity', domain_id:activeDomainId, type:activeType, description:desc, scheduled_date:sched});
  if (res.ok) {
    toast('Activity saved!','success');
    document.getElementById('modalDesc').value=''; document.getElementById('modalSched').value='';
    document.querySelectorAll('[data-lead-id="'+activeDomainId+'"] .act-pill').forEach(el => {
      el.textContent = (parseInt(el.textContent)+1)+' act'; el.classList.add('has-acts');
    });
    loadActivities(activeDomainId);
  } else toast(res.msg||'Failed.','error');
  btn.disabled=false; btn.innerHTML='<i class="fa fa-check"></i> Save';
}
async function loadActivities(did) {
  const list = document.getElementById('actList');
  list.innerHTML = '<div class="acts-loading"><div class="spinner"></div></div>';
  const res = await api({action:'get_activities', domain_id:did});
  if (!res.ok) { list.innerHTML='<div class="acts-loading" style="color:var(--red)">Failed.</div>'; return; }
  if (!res.activities.length) { list.innerHTML='<div class="acts-loading" style="color:var(--text-3)">No activities yet.</div>'; return; }
  const ti = {call:'fa-phone',note:'fa-note-sticky',meeting:'fa-calendar',followup:'fa-rotate-right'};
  const tl = {call:'Call',note:'Note',meeting:'Meeting',followup:'Follow-up'};
  list.innerHTML = res.activities.map(a => `
    <div class="act-item" id="act-${a.id}">
      <div class="act-icon ${a.type}"><i class="fa ${ti[a.type]||'fa-circle'}"></i></div>
      <div class="act-content">
        <div class="act-desc">${escHtml(a.description)}</div>
        <div class="act-meta">
          <span>${tl[a.type]||a.type}</span>
          <span>${fmtDate(a.created_at)}</span>
          ${a.scheduled_date?'<span>📅 '+fmtDate(a.scheduled_date)+'</span>':''}
        </div>
      </div>
      <button class="act-del" onclick="delAct(${a.id})"><i class="fa fa-trash"></i></button>
    </div>`).join('');
}
async function delAct(id) {
  if (!confirm('Delete?')) return;
  const res = await api({action:'delete_activity', activity_id:id});
  if (res.ok) { document.getElementById('act-'+id)?.remove(); toast('Deleted.','info'); }
}

setType('call');
</script>
</body>
</html>