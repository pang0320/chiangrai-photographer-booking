<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

flash('info', 'ย้ายการตั้งค่าหัวข้อหน้าแรกไปไว้ที่หน้าตั้งค่าระบบแล้ว');
redirect('/admin/settings.php');
