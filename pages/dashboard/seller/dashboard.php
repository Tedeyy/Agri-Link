<?php
session_start();
$firstname = isset($_SESSION['firstname']) && $_SESSION['firstname'] !== '' ? $_SESSION['firstname'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/dashboard.css">
    </head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="brand">Dashboard</div>
            <form class="search" method="get" action="#">
                <input type="search" name="q" placeholder="Search" />
            </form>
        </div>
        <div class="nav-right">
            <div class="greeting">hello <?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?></div>
            <a class="btn" href="../logout.php">Logout</a>
            <a class="profile" href="pages/profile.php" aria-label="Profile">
                <span class="avatar">👤</span>
            </a>
        </div>
    </nav>
    <div class="wrap">
        <div class="top">
            <div>
                <h1>Seller Dashboard</h1>
            </div>
            <div>
                <a class="btn" href="pages/createlisting.php">Create Listing</a>
            </div>
        </div>
        <div class="card">
            <p>Manage your listings and view recent activity here.</p>
        </div>

        <div class="card">
            <h3>Service Coverage Map</h3>
            <div class="mapbox" aria-label="Map placeholder"></div>
        </div>

        <div class="card">
            <h3>Livestock Aid (Sales Projection)</h3>
            <canvas id="salesLineChart" height="180"></canvas>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function(){
      var ctx = document.getElementById('salesLineChart');
      if (!ctx || !window.Chart) return;
      function monthLabels(){
        const now = new Date();
        const labels = [];
        for (let i = -3; i <= 1; i++) {
          const d = new Date(now.getFullYear(), now.getMonth()+i, 1);
          labels.push(d.toLocaleString('en', { month: 'short' }));
        }
        return labels;
      }
      const labels = monthLabels();
      const empty = labels.map(() => null);
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            { label: 'Cattle', data: empty, borderColor: '#8B4513', backgroundColor: 'transparent', tension: 0.3, spanGaps: true, pointRadius: 0 },
            { label: 'Goat', data: empty, borderColor: '#16a34a', backgroundColor: 'transparent', tension: 0.3, spanGaps: true, pointRadius: 0 },
            { label: 'Pigs', data: empty, borderColor: '#ec4899', backgroundColor: 'transparent', tension: 0.3, spanGaps: true, pointRadius: 0 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          plugins: { legend: { position: 'bottom' } },
          scales: { y: { beginAtZero: true, suggestedMin: 0 } }
        }
      });
    })();
    </script>
</body>
</html>