<?php

include 'includes/db.php';
require_once __DIR__ . '/includes/assets.php';

$sql = "SELECT * FROM reports ORDER BY report_id DESC";

$result = mysqli_query($conn, $sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(es_base_path()); ?>">
    <title>Admin — EchoShield AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(es_url('css/style.css')); ?>">
</head>
<body class="page-app">

<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="app-main container py-4 py-md-5">
    <h1 class="app-title mb-4">Admin monitoring</h1>
    <div class="app-panel app-table-wrap">
        <div class="table-responsive">
            <table class="table app-table table-borderless mb-0">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Message</th>
                        <th scope="col">Toxicity</th>
                        <th scope="col">Severity</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo (int) $row['report_id']; ?></td>
                            <td><?php echo htmlspecialchars((string) $row['harmful_message'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['toxicity_score'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['severity_level'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a class="btn btn-sm btn-app-danger" href="<?php echo htmlspecialchars(es_url('suspend.php?id=' . (int) $row['victim_id']), ENT_QUOTES, 'UTF-8'); ?>">Suspend</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
