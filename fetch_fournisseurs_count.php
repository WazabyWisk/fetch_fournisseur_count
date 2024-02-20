d<?php


function getTotalDistinctSlots($pdo) {
    try {
        $sql = "SELECT COUNT(DISTINCT nom) AS total_slots FROM baCoCo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_slots'];
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function generateSortLink($currentSortBy, $currentSortDirection, $column) {
    // Determine the new sort direction
    $newSortDirection = 'ASC';
    if ($currentSortBy == $column && $currentSortDirection == 'ASC') {
        $newSortDirection = 'DESC';
    }

    // Build the query parameters
    $queryParams = http_build_query(array_merge($_GET, [
        'sortBy' => $column,
        'sortDirection' => $newSortDirection
    ]));

    // Generate the complete URL
    $url = basename($_SERVER['PHP_SELF']) . '?' . $queryParams;

    return htmlspecialchars($url);
}

function getTotalDistinctFournisseurs($pdo) {
    try {
        // Prepare and execute the query to get the count of distinct Fournisseurs
        $sql = "SELECT COUNT(DISTINCT Fournisseur) AS total_fournisseurs FROM baCoCo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_fournisseurs'];
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function getTopAverageGainMaxByProvider($pdo, $limit = 5) {
    try {
        $sql = "SELECT fournisseur, AVG(gain_max_possible) AS average_gain_max FROM BaCOCO GROUP BY fournisseur ORDER BY average_gain_max DESC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        // Fetch all results
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function getAverageGamesPerProvider($pdo) {
    try {
        // This SQL query assumes that 'nom' is the name of the games, and you want the average count per provider
        $sql = "SELECT fournisseur, COUNT(nom) AS number_of_games FROM baCoCo GROUP BY fournisseur";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        // Fetch all results
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate the average
        $totalGames = 0;
        $count = 0;
        foreach ($results as $row) {
            $totalGames += $row['number_of_games'];
            $count++;
        }
        return $count > 0 ? ($totalGames / $count) : 0;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}


function getRandomIframe($pdo) {
    try {
        // Adjust the SQL query according to your actual table and column names
        $sql = "SELECT iframe FROM BaCOCO ORDER BY RANDOM() LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        // Fetch the result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['iframe'] : null;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function getMostRecentSlots($pdo, $limit = 8) {
    try {
        $sql = "SELECT * FROM BaCOCO ORDER BY date_premiere DESC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        // Fetch all results
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}



function handleBaCOCOTable($pdo, $search = '', $page = 1, $rowsPerPage = 10, $sortBy = 'nom', $sortDirection = 'ASC') {
    // Validate and sanitize the sortBy parameter to prevent SQL Injection
    $allowedSortColumns = ['nom', 'gain_max_possible', 'fournisseur', 'date_premiere', 'type', 'rtp', 'variance', 'frequence_coup', 'gain_max', 'mise_min', 'mise_max', 'disposition', 'voies_pari', 'taille_jeu', 'derniere_maj'];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'nom'; // Fallback to a default column
    }

    // Ensure sortDirection is either 'ASC' or 'DESC'
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

    // Handle JSON export request
    if (isset($_GET['export']) && $_GET['export'] == 'json') {
        $sql = "SELECT * FROM baCOCO WHERE nom ILIKE :search OR fournisseur ILIKE :search ORDER BY $sortBy $sortDirection";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['search' => "%$search%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            $rows = []; // Ensure $rows is an array
        }

        header('Content-Disposition: attachment; filename="baCOCO.json"');
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }

    // Pagination setup
    $offset = ($page - 1) * $rowsPerPage;

    // SQL query with dynamic sorting
    $sql = "SELECT * FROM baCOCO WHERE nom ILIKE :search OR fournisseur ILIKE :search ORDER BY $sortBy $sortDirection LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);

    // Binding parameters
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    // Fetching the results
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
        $rows = []; // Ensure $rows is an array
    }

    return $rows;
}



function getPaginationLinks($pdo, $search, $currentPage, $rowsPerPage) {
    // Calculate the total number of items
    $sql = "SELECT COUNT(*) FROM baCOCO WHERE nom LIKE :search OR fournisseur LIKE :search";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => "%$search%"]);
    $totalItems = $stmt->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($totalItems / $rowsPerPage);

    // Start building the HTML for pagination links
    $html = '<div class="pagination">';

    // Previous link
    if ($currentPage > 1) {
        $html .= '<a href="?page=' . ($currentPage - 1) . '&search=' . urlencode($search) . '">Previous</a>';
    }

    // Next link
    if ($currentPage < $totalPages) {
        $html .= '<a href="?page=' . ($currentPage + 1) . '&search=' . urlencode($search) . '">Next</a>';
    }

    $html .= '</div>';

    return $html;
}



// Include your existing database connection file here
// For example, if your connection file is db_connect.php, you would include it like this:
// include 'path/to/your/db_connect.php';

// Make sure that the included file has the PDO connection object ($pdo)
$dsn = 'pgsql:host=localhost;dbname=BaCOCO;port=5432';
$user = 'postgres';
$password = 'toor';
try {
    // Prepare and execute the query to get the count of distinct Fournisseurs
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If there is an error, output it (You might want to handle this more gracefully in a production environment)
    echo "Query failed: " . $e->getMessage();
}

?>
