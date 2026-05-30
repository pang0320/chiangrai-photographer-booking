<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * ตรวจสอบความผิดปกติของข้อมูลนำเข้า (เช่น XSS, SQL Injection)
 * @param mixed $input ข้อมูล Input แบบดิบที่ยังไม่ถูกประมวลผล
 * @return array ชุดข้อมูล (Array)
 */
function detectSuspiciousInput($input): array
{
    $value = '';
    if (is_array($input)) {
        $value = json_encode($input, JSON_UNESCAPED_UNICODE);
        if ($value === false) {
            $value = '';
        }
    } else {
        $value = (string)$input;
    }

    $decodedValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    $decodedValue = rawurldecode($decodedValue);
    $normalizedValue = strtolower($decodedValue);

    $matches = [];
    $score = 0;

    $patterns = [
        'xss_script_tag' => '/<\s*script\b/i',
        'xss_event_handler' => '/\bon[a-z]+\s*=/i',
        'xss_javascript_url' => '/javascript\s*:/i',
        'xss_data_html' => '/data\s*:\s*text\/html/i',
        'html_iframe_object' => '/<\s*(iframe|object|embed|applet|meta|link)\b/i',
        'sql_union_select' => '/\bunion\b\s+(all\s+)?\bselect\b/i',
        'sql_boolean_injection' => '/(\bor\b|\band\b)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        'sql_comment' => '/(--|#|\/\*)/',
        'sql_sleep_benchmark' => '/\b(sleep|benchmark|pg_sleep)\s*\(/i',
        'sql_write_read_file' => '/\b(load_file|into\s+outfile|into\s+dumpfile)\b/i',
        'sql_schema_probe' => '/\b(information_schema|mysql\.user|sys\.|performance_schema)\b/i',
        'path_traversal' => '/(\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e\\\\)/i',
        'shell_command' => '/[;&|`]\s*(cat|curl|wget|bash|sh|zsh|nc|netcat|python|perl|php)\b/i',
        'php_wrapper' => '/\b(php|data|expect|zip|phar|file):\/\//i',
        'template_injection' => '/(\{\{.*\}\}|\$\{.*\}|<%.*%>)/s',
        'ldap_injection' => '/[()&|!]=?/',
    ];

    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $decodedValue)) {
            $matches[] = $name;
            $score += 10;
        }
    }

    if (strlen($value) > 5000) {
        $matches[] = 'oversized_input';
        $score += 5;
    }

    if (substr_count($normalizedValue, '../') >= 2) {
        $matches[] = 'repeated_path_traversal';
        $score += 10;
    }

    if (preg_match_all('/%[0-9a-f]{2}/i', $value, $encodedMatches) > 20) {
        $matches[] = 'heavy_url_encoding';
        $score += 5;
    }

    $isSuspicious = false;
    if ($score > 0) {
        $isSuspicious = true;
    }

    return [
        'is_suspicious' => $isSuspicious,
        'score' => $score,
        'matches' => array_values(array_unique($matches)),
        'preview' => substr($decodedValue, 0, 300),
    ];
}

/**
 * สแกนคำขอ (GET, POST, COOKIE) ทั้งหมดเพื่อหาภัยคุกคามด้านความปลอดภัย
 * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ สแกนคำขอ (GET, POST, COOKIE) ทั้งหมดเพื่อหาภัยคุกคามด้านความปลอดภัย
 * @return array ชุดข้อมูล (Array)
 */
function scanRequestForThreats(): array
{
    $sources = [
        'GET' => $_GET,
        'POST' => $_POST,
        'COOKIE' => $_COOKIE,
    ];

    $threats = [];

    foreach ($sources as $sourceName => $sourceData) {
        scanSecuritySource($sourceName, $sourceData, '', $threats);
    }

    if (!empty($threats)) {
        logSecurityEvent('suspicious_request_detected', [
            'threat_count' => count($threats),
            'threats' => $threats,
        ]);
    }

    return [
        'is_suspicious' => !empty($threats),
        'threat_count' => count($threats),
        'threats' => $threats,
    ];
}

/**
 * บันทึกเหตุการณ์ด้านความปลอดภัยลงใน Activity Log
 * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ บันทึกเหตุการณ์ด้านความปลอดภัยลงใน Activity Log
 * @param string $eventType ประเภทหรือชื่อเรียกเหตุการณ์เตือนภัย
 * @param array $context ข้อมูลแวดล้อมเพิ่มเติม (Context)
 * @return void ไม่มีการคืนค่า
 */
function logSecurityEvent(string $eventType, array $context = []): void
{
    $userId = null;
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    $description = json_encode($context, JSON_UNESCAPED_UNICODE);
    if ($description === false) {
        $description = '';
    }
    $description = substr($description, 0, 2000);

    $ipAddress = null;
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = substr((string)$_SERVER['REMOTE_ADDR'], 0, 64);
    }

    $userAgent = '';
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $userAgent = substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255);
    }

    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $userId,
        $eventType,
        'security',
        null,
        $ipAddress,
        $userAgent,
        $description,
    ]);
}

/**
 * ฟังก์ชันช่วยในการสแกนข้อมูลจากแหล่งต่างๆ แบบ recursive
 * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ ฟังก์ชันช่วยในการสแกนข้อมูลจากแหล่งต่างๆ แบบ recursive
 * @param string $sourceName แหล่งที่มาของชุดข้อมูล (เช่น GET, POST)
 * @param mixed $value ข้อมูลที่ต้องการประมวลผล
 * @param string $path เส้นทาง URL หรือ Path
 * @param array &$threats ตัวแปรแบบ Reference สำหรับเก็บรายการภัยคุกคามสะสม
 * @return void ไม่มีการคืนค่า
 */
function scanSecuritySource(string $sourceName, $value, string $path, array &$threats): void
{
    if (is_array($value)) {
        foreach ($value as $key => $childValue) {
            $childPath = (string)$key;
            if ($path !== '') {
                $childPath = $path . '.' . (string)$key;
            }
            scanSecuritySource($sourceName, $childValue, $childPath, $threats);
        }
        return;
    }

    $fieldName = $path;
    if ($fieldName === '') {
        $fieldName = 'value';
    }

    if (isSensitiveSecurityField($fieldName)) {
        return;
    }

    $result = detectSuspiciousInput($value);
    if ($result['is_suspicious']) {
        $threats[] = [
            'source' => $sourceName,
            'field' => $fieldName,
            'score' => $result['score'],
            'matches' => $result['matches'],
            'preview' => $result['preview'],
        ];
    }
}

/**
 * ตรวจสอบว่าเป็นฟิลด์ข้อมูลที่ละเอียดอ่อนหรือไม่ (เช่น รหัสผ่าน) เพื่อเลี่ยงการสแกนหรือบันทึก log
 * @param string $fieldName ชื่อฟิลด์หรือตัวแปรที่รับมา
 * @return bool ค่าความจริง (Boolean)
 */
function isSensitiveSecurityField(string $fieldName): bool
{
    $fieldName = strtolower($fieldName);
    $sensitiveWords = [
        'password',
        'password_confirmation',
        'token',
        'csrf',
        'remember_token',
        'secret',
    ];

    foreach ($sensitiveWords as $word) {
        if (strpos($fieldName, $word) !== false) {
            return true;
        }
    }

    return false;
}
