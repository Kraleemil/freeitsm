<?php
/**
 * The mapping for one connection, plus the local lists it maps FROM (companies,
 * departments, ticket types, priorities) so the screen can label every row.
 *
 * The local lists are read here rather than on the settings page itself because
 * the page is shared across providers and only needs them when the mapping
 * screen is opened.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/admin_api_guard.php';
require_once '../../includes/encryption.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/integrations/integrations.php';

header('Content-Type: application/json');

$conn = connectToDatabase();

if (!integrationsSchemaReady($conn)) {
    echo json_encode(['success' => false, 'error' => 'Run Database Verification first.']);
    exit;
}

$connectionId = (int)($_GET['connection_id'] ?? 0);
if ($connectionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'No connection given.']);
    exit;
}

/** A lookup list, tolerant of a table that is not there on every install. */
function mappingList(PDO $conn, string $sql): array
{
    try {
        return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

try {
    echo json_encode([
        'success' => true,
        // False when the install has not run Database Verification since this
        // release: the screen says so rather than silently saving nothing.
        'schema_ready' => integrationsMapSchemaReady($conn),
        'maps'         => integrationsLoadMaps($conn, $connectionId),
        'local'        => [
            'companies'    => mappingList($conn, "SELECT id, name FROM tenants WHERE is_active = 1 ORDER BY name"),
            'departments'  => mappingList($conn, "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"),
            'ticket_types' => mappingList($conn, "SELECT id, name FROM ticket_types WHERE is_active = 1 ORDER BY name"),
            'priorities'   => mappingList($conn, "SELECT id, name FROM ticket_priorities WHERE is_active = 1 ORDER BY display_order, name"),
        ],
    ]);
} catch (Exception $e) {
    error_log('get_mapping: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not load the mapping.']);
}
