<?php
// dashboard.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db = db();

$stats = [
    'students'    => $db->query("SELECT COUNT(*) FROM students  WHERE status='Active'")->fetchColumn(),
    'faculty'     => $db->query("SELECT COUNT(*) FROM faculty   WHERE status='Active'")->fetchColumn(),
    'courses'     => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'sections'    => $db->query("SELECT COUNT(*) FROM class_sections WHERE status='Open'")->fetchColumn(),
    'enrollments' => $db->query("SELECT COUNT(*) FROM enrollments WHERE status='Enrolled'")->fetchColumn(),
];

$byDept = $db->query("
    SELECT d.dept_name, COUNT(s.student_id) AS cnt
    FROM departments d
    LEFT JOIN students s ON d.department_id=s.department_id AND s.status='Active'
    GROUP BY d.department_id ORDER BY cnt DESC LIMIT 6
")->fetchAll();
$maxDept = max(1, max(array_column($byDept, 'cnt')));

$recent = $db->query("
    SELECT st.student_number, CONCAT(st.first_name,' ',st.last_name) AS student_name,
           c.course_code, cs.section_code, e.enrollment_date, e.status
    FROM enrollments e
    JOIN students st ON e.student_id=st.student_id
    JOIN class_sections cs ON e.section_id=cs.section_id
    JOIN courses c ON cs.course_id=c.course_id
    ORDER BY e.enrollment_id DESC LIMIT 6
")->fetchAll();

layoutHead('Dashboard');
layoutSidebar('dashboard.php');
?>

<?php flashBanner(); ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">Welcome back, <?= sanitize(currentUser()['name'] ?? '') ?></p>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Active Students</div>
    <div class="stat-value"><?= number_format($stats['students']) ?></div>
    <div class="stat-hint">Currently enrolled</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Faculty Members</div>
    <div class="stat-value"><?= number_format($stats['faculty']) ?></div>
    <div class="stat-hint">Active instructors</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Total Courses</div>
    <div class="stat-value"><?= number_format($stats['courses']) ?></div>
    <div class="stat-hint">In catalog</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-label">Open Sections</div>
    <div class="stat-value"><?= number_format($stats['sections']) ?></div>
    <div class="stat-hint">This semester</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Enrollments</div>
    <div class="stat-value"><?= number_format($stats['enrollments']) ?></div>
    <div class="stat-hint">Active this term</div>
  </div>
</div>

<!-- MAIN GRID -->
<div class="dash-grid">
  <!-- Students by dept -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Students by Department</span>
    </div>
    <div class="bar-list">
      <?php foreach ($byDept as $row): ?>
      <div class="bar-item">
        <div class="bar-top">
          <span><?= sanitize($row['dept_name']) ?></span>
          <span><?= $row['cnt'] ?></span>
        </div>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= round($row['cnt']/$maxDept*100) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent enrollments -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Enrollments</span>
      <a href="/university/enrollments.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= sanitize($r['student_name']) ?></div>
              <div style="font-size:12px;color:#94a3b8"><?= sanitize($r['student_number']) ?></div>
            </td>
            <td><span class="td-mono"><?= sanitize($r['course_code']) ?></span></td>
            <td style="font-size:12px;color:#64748b"><?= $r['enrollment_date'] ?></td>
            <td><?php
              $cls = match($r['status']) {
                'Enrolled' => 'success', 'Completed' => 'info',
                'Dropped'  => 'muted',  'Failed' => 'danger', default => 'muted'
              };
              echo "<span class=\"badge badge-{$cls}\">{$r['status']}</span>";
            ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?>
          <tr><td colspan="4" class="table-empty">No enrollments yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php layoutEnd(); ?>