<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout-student.php';

requireLogin();
$db = db();

$user = currentUser();

if (!isset($user['student_id']) || !$user['student_id']) {
    layoutHead('Student Dashboard');
    layoutSidebar('dashboard-students.php');
    echo "<div class='alert alert-error'>No student ID found in session.</div>";
    layoutEnd();
    exit;
}

// Full student profile with department
$stmt = $db->prepare("
    SELECT s.*, d.dept_name
    FROM students s
    LEFT JOIN departments d ON s.department_id = d.department_id
    WHERE s.student_id = ?
");
$stmt->execute([$user['student_id']]);
$student = $stmt->fetch();

if (!$student) {
    layoutHead('Student Dashboard');
    layoutSidebar('dashboard-students.php');
    echo "<div class='alert alert-error'>Student record not found.</div>";
    layoutEnd();
    exit;
}

// All enrollments with full details
$stmt = $db->prepare("
    SELECT
        c.course_code,
        c.course_name,
        c.units,
        cs.section_code,
        cs.schedule,
        cs.semester,
        cs.school_year,
        CONCAT(f.first_name, ' ', f.last_name) AS instructor,
        e.midterm_grade,
        e.final_grade,
        e.status,
        e.enrollment_id,
        e.enrollment_date
    FROM enrollments e
    LEFT JOIN class_sections cs ON e.section_id = cs.section_id
    LEFT JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
    WHERE e.student_id = ?
    ORDER BY cs.school_year DESC, cs.semester, c.course_code
");
$stmt->execute([$student['student_id']]);
$allEnrollments = $stmt->fetchAll();

$currentSubjects = array_values(array_filter($allEnrollments, fn($e) => $e['status'] === 'Enrolled'));
$historySubjects = array_values(array_filter($allEnrollments, fn($e) => $e['status'] !== 'Enrolled'));

// Total units currently enrolled
$totalUnitsEnrolled = 0;
foreach ($currentSubjects as $s) { $totalUnitsEnrolled += (int)($s['units'] ?? 3); }

// Compute GWA from all rows that have a final grade
$gwa = null;
$gradedRows = array_filter($allEnrollments, fn($e) => $e['final_grade'] !== null);
if ($gradedRows) {
    $wp = 0; $wu = 0;
    foreach ($gradedRows as $g) {
        $u = (int)($g['units'] ?? 3);
        $wp += $g['final_grade'] * $u;
        $wu += $u;
    }
    if ($wu > 0) $gwa = $wp / $wu;
}

$yearLabels = [1=>'1st Year', 2=>'2nd Year', 3=>'3rd Year', 4=>'4th Year', 5=>'5th Year'];

layoutHead('Student Dashboard');
layoutSidebar('dashboard-students.php');
?>

<style>
/* ── Tab bar ── */
.stab-bar{display:flex;gap:2px;margin-bottom:24px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:5px;box-shadow:var(--shadow);}
.stab{flex:1;padding:9px 10px;border:none;background:none;border-radius:7px;font-size:13.5px;font-weight:600;color:var(--text-2);cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:7px;}
.stab:hover{color:var(--navy);background:#f1f5f9;}
.stab.on{background:linear-gradient(135deg,#065f46,#047857);color:#fff;box-shadow:0 2px 8px rgba(6,95,70,.25);}

/* ── Summary strip ── */
.sumstrip{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:14px;margin-bottom:22px;}
.sumcard{background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px 18px;box-shadow:var(--shadow);border-top:3px solid var(--blue);}
.sumcard.g{border-top-color:var(--green);}
.sumcard.a{border-top-color:var(--amber);}
.sumcard.r{border-top-color:var(--red);}
.sum-v{font-family:var(--font-head);font-size:30px;color:var(--navy);line-height:1;}
.sum-l{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-2);margin-top:4px;}

/* ── Schedule styles ── */
.sched-code{font-family:'Courier New',monospace;font-size:12px;font-weight:700;color:var(--navy);}
.sched-name{font-size:13px;font-weight:600;color:var(--text);}
.sched-instr{font-size:12px;color:var(--text-3);}
.daytag{display:inline-block;padding:1px 7px;border-radius:5px;font-size:11px;font-weight:700;background:#d1fae5;color:#065f46;margin:1px 2px 1px 0;}
.timepill{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:12px;font-weight:600;background:#eff6ff;color:var(--blue);margin-top:4px;}
.prog-bar{display:flex;flex-wrap:wrap;border-radius:10px;overflow:hidden;border:1px solid var(--border);background:#fff;margin-bottom:18px;}
.prog-cell{flex:1;min-width:130px;padding:14px 18px;border-right:1px solid var(--border);}
.prog-cell:last-child{border-right:none;}

/* ── Profile ── */
.prof-top{display:flex;align-items:center;gap:20px;padding:24px;border-bottom:1px solid var(--border);}
.prof-ava{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,#065f46,#10b981);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-1px;}
.prof-nm{font-family:var(--font-head);font-size:20px;font-weight:600;color:var(--navy);}
.prof-sn{font-size:13px;color:var(--text-2);margin-top:2px;font-family:'Courier New',monospace;}
.prof-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
.prof-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;padding:20px 24px;}
.pfi{background:#f8fafc;border-radius:8px;padding:12px 14px;}
.pfi.full{grid-column:1/-1;}
.pfi-l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);}
.pfi-v{font-size:14px;font-weight:600;color:var(--navy);margin-top:3px;}
.acad-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;padding:0 24px 24px;}
.acad-box{background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;box-shadow:var(--shadow);}
.acad-n{font-family:var(--font-head);font-size:28px;color:var(--navy);}
.acad-l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-top:4px;}
.acad-head{padding:16px 24px 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);border-top:1px solid var(--border);}

.badge-gold{background:var(--gold-light);color:var(--gold);}
.no-data{text-align:center;padding:44px 20px;color:var(--text-3);}
.no-data .ico{font-size:36px;margin-bottom:10px;}
</style>

<!-- PAGE HEADER -->
<div class="page-header">
  <div>
    <h1 class="page-title">Student Portal</h1>
    <p class="page-sub">
      <?= sanitize($student['first_name'].' '.$student['last_name']) ?>
      &nbsp;·&nbsp; <?= sanitize($student['student_number']) ?>
    </p>
  </div>
</div>

<!-- TAB BAR -->
<div class="stab-bar">
  <button class="stab on" id="btn-grades"   onclick="goTab('grades',this)">📋 Grades</button>
  <button class="stab"    id="btn-schedule" onclick="goTab('schedule',this)">🗓 Schedule</button>
  <button class="stab"    id="btn-profile"  onclick="goTab('profile',this)">👤 Profile</button>
</div>

<!-- ══════════════ GRADES TAB ══════════════ -->
<div id="tab-grades">

  <div class="sumstrip">
    <div class="sumcard">
      <div class="sum-v"><?= $gwa !== null ? number_format($gwa,2) : '—' ?></div>
      <div class="sum-l">GWA</div>
    </div>
    <div class="sumcard g">
      <div class="sum-v"><?= count($currentSubjects) ?></div>
      <div class="sum-l">Enrolled</div>
    </div>
    <div class="sumcard a">
      <div class="sum-v"><?= $totalUnitsEnrolled ?></div>
      <div class="sum-l">Units</div>
    </div>
    <div class="sumcard r">
      <div class="sum-v"><?= count(array_filter($allEnrollments, fn($e)=>$e['status']==='Dropped')) ?></div>
      <div class="sum-l">Dropped</div>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <span class="card-title">Current Subjects</span>
      <span class="badge badge-success"><?= count($currentSubjects) ?> subject<?= count($currentSubjects)!=1?'s':'' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Code</th><th>Subject</th><th>Units</th><th>Section</th><th>Instructor</th><th>Midterm</th><th>Final</th><th>Average</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($currentSubjects): foreach ($currentSubjects as $g): ?>
          <tr>
            <td class="td-mono"><?= sanitize($g['course_code']??'—') ?></td>
            <td style="font-weight:600"><?= sanitize($g['course_name']??'—') ?></td>
            <td><span class="badge badge-gold"><?= $g['units']??'—' ?></span></td>
            <td style="font-size:12px"><?= sanitize($g['section_code']??'—') ?></td>
            <td style="font-size:12px;color:var(--text-2)"><?= sanitize($g['instructor']??'—') ?></td>
            <td><?php if($g['midterm_grade']!==null) echo "<span class='".($g['midterm_grade']>=75?'grade-pass':'grade-fail')."'>".number_format($g['midterm_grade'],2)."</span>"; else echo "<span style='color:#cbd5e1'>—</span>"; ?></td>
            <td><?php if($g['final_grade']!==null) echo "<span class='".($g['final_grade']>=75?'grade-pass':'grade-fail')."'>".number_format($g['final_grade'],2)."</span>"; else echo "<span style='color:#cbd5e1'>—</span>"; ?></td>
            <td><?php
              $mid=$g['midterm_grade']; $fin=$g['final_grade'];
              if($mid!==null&&$fin!==null){$avg=($mid+$fin)/2;echo "<span class='".($avg>=75?'grade-pass':'grade-fail')."'>".number_format($avg,2)."</span>";}
              elseif($fin!==null){echo "<span class='".($fin>=75?'grade-pass':'grade-fail')."'>".number_format($fin,2)."</span>";}
              else echo "<span class='badge badge-muted'>Pending</span>";
            ?></td>
            <td><span class="badge badge-success">Enrolled</span></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="9" class="table-empty">No active enrollments this semester.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($historySubjects): ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Grade History</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Code</th><th>Subject</th><th>Units</th><th>Semester</th><th>Final Grade</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($historySubjects as $g): ?>
          <tr>
            <td class="td-mono"><?= sanitize($g['course_code']??'—') ?></td>
            <td><?= sanitize($g['course_name']??'—') ?></td>
            <td><span class="badge badge-gold"><?= $g['units']??'—' ?></span></td>
            <td style="font-size:12px;color:var(--text-2)"><?= sanitize($g['semester']??'') ?> <?= sanitize($g['school_year']??'') ?></td>
            <td><?php $fin=$g['final_grade']; if($fin!==null) echo "<span class='".($fin>=75?'grade-pass':'grade-fail')."'>".number_format($fin,2)."</span>"; else echo "<span style='color:#cbd5e1'>—</span>"; ?></td>
            <td><?php $st=$g['status']??'N/A'; $cls=match($st){'Completed'=>'info','Dropped'=>'muted','Failed'=>'danger',default=>'muted'}; echo "<span class='badge badge-$cls'>$st</span>"; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══════════════ SCHEDULE TAB ══════════════ -->
<div id="tab-schedule" style="display:none">

  <?php if ($currentSubjects): ?>

  <div class="prog-bar" style="margin-bottom:18px;box-shadow:var(--shadow);">
    <div class="prog-cell">
      <div class="sum-l">Department</div>
      <div style="font-size:14px;font-weight:600;color:var(--navy);margin-top:3px"><?= sanitize($student['dept_name']??'—') ?></div>
    </div>
    <div class="prog-cell">
      <div class="sum-l">Year Level</div>
      <div style="font-size:14px;font-weight:600;color:var(--navy);margin-top:3px">
        <?php $yr=$student['year_level']??null; echo isset($yearLabels[$yr])?$yearLabels[$yr]:'—'; ?>
      </div>
    </div>
    <div class="prog-cell">
      <div class="sum-l">Subjects</div>
      <div style="font-size:14px;font-weight:600;color:var(--navy);margin-top:3px"><?= count($currentSubjects) ?></div>
    </div>
    <div class="prog-cell">
      <div class="sum-l">Total Units</div>
      <div style="font-size:14px;font-weight:600;color:var(--navy);margin-top:3px"><?= $totalUnitsEnrolled ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Weekly Class Schedule</span>
      <span class="badge badge-info">Current Semester</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Course Code</th><th>Subject Name</th><th>Section</th><th>Days & Time</th><th>Instructor</th><th>Units</th></tr>
        </thead>
        <tbody>
          <?php foreach ($currentSubjects as $sub):
            $rawSched = trim($sub['schedule'] ?? '');
            $dayTokens = []; $timeStr = '';
            if ($rawSched) {
              preg_match('/^([A-Za-z]+)\s+(.+)$/', $rawSched, $m);
              $dStr = $m[1] ?? $rawSched;
              $timeStr = $m[2] ?? '';
              foreach (['Th','Su','M','T','W','F','S'] as $d) {
                if (str_contains($dStr,$d)) { $dayTokens[]=$d; $dStr=str_replace($d,'',$dStr); }
              }
              if (!$dayTokens && trim($dStr)) $dayTokens=[trim($dStr)];
            }
          ?>
          <tr>
            <td><span class="sched-code"><?= sanitize($sub['course_code']??'—') ?></span></td>
            <td><div class="sched-name"><?= sanitize($sub['course_name']??'—') ?></div></td>
            <td><span class="td-mono" style="font-size:12px"><?= sanitize($sub['section_code']??'—') ?></span></td>
            <td>
              <?php if ($rawSched): ?>
                <div><?php foreach($dayTokens as $d): ?><span class="daytag"><?= htmlspecialchars($d) ?></span><?php endforeach; ?></div>
                <?php if ($timeStr): ?><div class="timepill">🕐 <?= sanitize($timeStr) ?></div><?php endif; ?>
              <?php else: ?><span style="color:#cbd5e1;font-size:12px">Not set</span><?php endif; ?>
            </td>
            <td><div class="sched-instr"><?= sanitize($sub['instructor']??'—') ?></div></td>
            <td><span class="badge badge-gold"><?= $sub['units']??'—' ?> u</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f8fafc">
            <td colspan="4" style="padding:10px 16px;font-size:12px;color:var(--text-2);font-weight:600"><?= count($currentSubjects) ?> subject<?= count($currentSubjects)!=1?'s':'' ?> enrolled</td>
            <td colspan="2" style="padding:10px 16px"><span class="badge badge-info"><?= $totalUnitsEnrolled ?> total units</span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php else: ?>
  <div class="card">
    <div class="no-data">
      <div class="ico">📅</div>
      <div style="font-weight:600;color:var(--text-2)">No active schedule</div>
      <div style="font-size:13px;margin-top:4px">You have no subjects enrolled this semester.</div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══════════════ PROFILE TAB ══════════════ -->
<div id="tab-profile" style="display:none">
  <div class="card">

    <div class="prof-top">
      <div class="prof-ava">
        <?= strtoupper(substr($student['first_name']??'S',0,1).substr($student['last_name']??'',0,1)) ?>
      </div>
      <div>
        <div class="prof-nm"><?= sanitize(trim(($student['first_name']??'').' '.($student['middle_name']??'').' '.($student['last_name']??''))) ?></div>
        <div class="prof-sn"><?= sanitize($student['student_number']??'') ?></div>
        <div class="prof-tags">
          <?php $st=$student['status']??'Active'; echo "<span class='badge badge-".($st==='Active'?'success':'muted')."'>".sanitize($st)."</span>"; ?>
          <?php if(!empty($student['dept_name'])) echo "<span class='badge badge-info'>".sanitize($student['dept_name'])."</span>"; ?>
          <?php $yr=$student['year_level']??null; if($yr&&isset($yearLabels[$yr])) echo "<span class='badge badge-gold'>".$yearLabels[$yr]."</span>"; ?>
        </div>
      </div>
    </div>

    <div class="prof-grid">
      <div class="pfi"><div class="pfi-l">Student Number</div><div class="pfi-v td-mono" style="font-size:13px"><?= sanitize($student['student_number']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">First Name</div><div class="pfi-v"><?= sanitize($student['first_name']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Middle Name</div><div class="pfi-v"><?= sanitize($student['middle_name']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Last Name</div><div class="pfi-v"><?= sanitize($student['last_name']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Email</div><div class="pfi-v" style="font-size:13px"><?= sanitize($student['email']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Phone</div><div class="pfi-v"><?= sanitize($student['phone']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Date of Birth</div><div class="pfi-v"><?php $dob=$student['date_of_birth']??($student['birthdate']??null); echo $dob?date('F j, Y',strtotime($dob)):'—'; ?></div></div>
      <div class="pfi"><div class="pfi-l">Gender</div><div class="pfi-v"><?= sanitize($student['gender']??'—') ?></div></div>
      <div class="pfi full"><div class="pfi-l">Address</div><div class="pfi-v" style="font-size:13px"><?= sanitize($student['address']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Department</div><div class="pfi-v"><?= sanitize($student['dept_name']??'—') ?></div></div>
      <div class="pfi"><div class="pfi-l">Year Level</div><div class="pfi-v"><?= isset($yearLabels[$yr])?$yearLabels[$yr]:($yr??'—') ?></div></div>
    </div>

    <div class="acad-head">Academic Summary</div>
    <div class="acad-strip">
      <div class="acad-box"><div class="acad-n"><?= count($allEnrollments) ?></div><div class="acad-l">Total Enrollments</div></div>
      <div class="acad-box"><div class="acad-n" style="color:var(--green)"><?= count($currentSubjects) ?></div><div class="acad-l">Currently Enrolled</div></div>
      <div class="acad-box"><div class="acad-n" style="color:var(--blue)"><?= count(array_filter($allEnrollments,fn($e)=>$e['status']==='Completed')) ?></div><div class="acad-l">Completed</div></div>
      <div class="acad-box"><div class="acad-n" style="color:var(--amber)"><?= $gwa!==null?number_format($gwa,2):'—' ?></div><div class="acad-l">GWA</div></div>
    </div>

  </div>
</div>

<script>
function goTab(name, btn) {
  ['grades','schedule','profile'].forEach(function(t){
    document.getElementById('tab-'+t).style.display='none';
    document.getElementById('btn-'+t).classList.remove('on');
  });
  document.getElementById('tab-'+name).style.display='block';
  btn.classList.add('on');
}
</script>

<?php layoutEnd(); ?>