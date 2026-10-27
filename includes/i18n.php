<?php
function t($key) {
    static $messages;
    if ($messages === null) $messages = json_decode(file_get_contents(__DIR__ . '/lang/kk.json'), true, 512, JSON_THROW_ON_ERROR);
    return $messages[$key ?? ''] ?? ($key ?? '');
}

function clinicToday() {
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Qyzylorda')))->format('Y-m-d');
}

function validClinicDate($value) {
    if (!is_string($value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Qyzylorda'));
    return $date && $date->format('Y-m-d') === $value;
}

function displayDate($value) {
    if (!$value) return t('N/A');
    try { return (new DateTimeImmutable($value))->format('d.m.Y'); }
    catch (Exception $e) { return t('N/A'); }
}

function displayTime($value) { return substr($value ?? '', 0, 5); }

function json_attr(array $values) {
    return h(json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function statusBadge($status) {
    $colors = ['Scheduled' => 'blue', 'Completed' => 'green', 'Cancelled' => 'secondary'];
    return '<span class="badge bg-' . ($colors[$status] ?? 'secondary') . '-lt">' . h(t($status)) . '</span>';
}
