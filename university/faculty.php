<?php
// faculty.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $d = ['employee_number'=>trim($_POST['employee_number']??''),'first_name'=>trim($_POST['first_name']??''),
              'last_name'=>trim($_POST['last_name']??''),'email'=>trim($_POST['email']??''),
              'phone'=>trim($_POST['phone']??''),'department_id'=>(int)($_POST['department_id']??0),
              'position'=>trim($_POST['position']??''),'status'=>trim($_POST['status']??'Active'),
              'hire_date'=>trim($_POST['hire_date']??'')];
        if (!$d['employee_number']||!$d['first_name']||!$d['last_name']||!$d['email']||!$d['department_id']||!$d['position']||!$d['hire_date']) {
            flash('error','All required fields must be filled.');
        } else {
            try {
                if ($action==='add') {
                    $db->prepare("INSERT INTO faculty(employee_number,first_name,last_name,email,phone,department_id,position,status,hire_date)VALUES(?,?,?,?,?,?,?,?,?)")->execute(array_values($d));
                    flash('success','Faculty member added.');
                } else {
                    $id=(int)($_POST['faculty_id']??0);
                    $db->prepare("UPDATE faculty SET employee_number=?,first_name=?,last_name=?,email=?,phone=?,department_id=?,position=?,status=?,hire_date=? WHERE faculty_id=?")->execute([...array_values($d),$id]);
                    flash('success','Faculty updated.');
                }
            } catch(PDOException $e){ flash('error','Employee # or email already exists.'); }
        }
    } elseif ($action==='delete') {
        $db->prepare("UPDATE faculty SET status='Terminated' WHERE faculty_id=?")->execute([(int)($_POST['faculty_id']??0)]);
        flash('success','Faculty deactivated.');
    }
    header('Location: /university/faculty.php'); exit;
}

$editData=null;
if(isset($_GET['edit'])){$s=$db->prepare("SELECT * FROM faculty WHERE faculty_id=?");$s->execute([(int)$_GET['edit']]);$editData=$s->fetch();}

$search=trim($_GET['search']??''); $deptF=(int)($_GET['dept']??0); $statusF=$_GET['status']??''; $page=max(1,(int)($_GET['page']??1)); $perPage=10;
$where=['1=1'];$params=[];
if($search){$where[]="(f.first_name LIKE ? OR f.last_name LIKE ? OR f.employee_number LIKE ?)";$like="%$search%";array_push($params,$like,$like,$like);}
if($deptF){$where[]='f.department_id=?';$params[]=$deptF;}
if($statusF){$where[]='f.status=?';$params[]=$statusF;}
$w=implode(' AND ',$where);
$cnt=$db->prepare("SELECT COUNT(*) FROM faculty f WHERE $w");$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$totalPages=max(1,ceil($total/$perPage));$offset=($page-1)*$perPage;
$stmt=$db->prepare("SELECT f.*,d.dept_name FROM faculty f JOIN departments d ON f.department_id=d.department_id WHERE $w ORDER BY f.last_name,f.first_name LIMIT $perPage OFFSET $offset");
$stmt->execute($params);$rows=$stmt->fetchAll();
$departments=$db->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();
$positions=['Instructor','Assistant Professor','Associate Professor','Professor','Dean'];

layoutHead('Faculty'); layoutSidebar('faculty.php');
if($editData) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("edit"));</script>';
flashBanner();
?>
<div class="page-header">
  <div><h1 class="page-title">Faculty</h1><p class="page-sub"><?=number_format($total)?> member<?=$total!=1?'s':''?></p></div>
  <button class="btn btn-primary" onclick="openModal('add')">+ Add Faculty</button>
</div>
<div class="card">
  <form method="GET" class="filters">
    <input type="text" name="search" class="filter-input" placeholder="Search name or employee #…" value="<?=htmlspecialchars($search)?>">
    <select name="dept" class="filter-select"><option value="">All Departments</option>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=$deptF==$d['department_id']?'selected':''?>><?=sanitize($d['dept_name'])?></option><?php endforeach; ?>
    </select>
    <select name="status" class="filter-select"><option value="">All Status</option>
      <?php foreach(['Active','On Leave','Retired','Terminated'] as $s): ?><option <?=$statusF===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="/university/faculty.php" class="btn btn-outline btn-sm">Reset</a>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Emp #</th><th>Name</th><th>Position</th><th>Department</th><th>Email</th><th>Hire Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><span class="td-mono"><?=sanitize($r['employee_number'])?></span></td>
        <td><div style="font-weight:600"><?=sanitize($r['first_name'].' '.$r['last_name'])?></div></td>
        <td><span class="badge badge-info"><?=sanitize($r['position'])?></span></td>
        <td><?=sanitize($r['dept_name'])?></td>
        <td style="font-size:13px"><?=sanitize($r['email'])?></td>
        <td style="font-size:12px;color:#64748b"><?=$r['hire_date']?></td>
        <td><?php $c=match($r['status']){'Active'=>'success','On Leave'=>'warning','Retired'=>'muted',default=>'danger'};echo "<span class='badge badge-$c'>{$r['status']}</span>"; ?></td>
        <td><div class="td-actions">
          <a href="?edit=<?=$r['faculty_id']?>" class="btn-icon">✏️ Edit</a>
          <button class="btn-icon btn-del" onclick="confirmDelete(<?=$r['faculty_id']?>,'<?=sanitize($r['first_name'].' '.$r['last_name'])?>')">🗑 Remove</button>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="8" class="table-empty">No faculty found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if($totalPages>1): $qs=http_build_query(array_filter(['search'=>$search,'dept'=>$deptF,'status'=>$statusF]));$base='/university/faculty.php?'.$qs.'&page='; ?>
  <div class="pagination"><span class="info"><?=$offset+1?>–<?=min($offset+$perPage,$total)?> of <?=$total?></span>
    <a href="<?=$base.max(1,$page-1)?>" class="page-link <?=$page<=1?'disabled':''?>">← Prev</a>
    <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?><a href="<?=$base.$p?>" class="page-link <?=$p==$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    <a href="<?=$base.min($totalPages,$page+1)?>" class="page-link <?=$page>=$totalPages?'disabled':''?>">Next →</a>
  </div>
  <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add"><div class="modal">
  <div class="modal-header"><span class="modal-title">Add Faculty Member</span><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group"><label class="form-label">Employee # *</label><input type="text" name="employee_number" class="form-control" placeholder="EMP-001" required></div>
    <div class="form-group"><label class="form-label">Hire Date *</label><input type="date" name="hire_date" class="form-control" required></div>
    <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
    <div class="form-group full"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
    <div class="form-group"><label class="form-label">Department *</label><select name="department_id" class="form-control" required><option value="">— Select —</option>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>"><?=sanitize($d['dept_name'])?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label class="form-label">Position *</label><select name="position" class="form-control" required><option value="">— Select —</option>
      <?php foreach($positions as $p): ?><option><?=$p?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control">
      <?php foreach(['Active','On Leave','Retired','Terminated'] as $s): ?><option><?=$s?></option><?php endforeach; ?></select></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-primary">Save Faculty</button></div>
  </form>
</div></div>

<?php if($editData): ?>
<div class="modal-overlay" id="modal-edit"><div class="modal">
  <div class="modal-header"><span class="modal-title">Edit Faculty</span><button class="modal-close" onclick="window.location='/university/faculty.php'">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="faculty_id" value="<?=$editData['faculty_id']?>">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group"><label class="form-label">Employee # *</label><input type="text" name="employee_number" class="form-control" value="<?=sanitize($editData['employee_number'])?>" required></div>
    <div class="form-group"><label class="form-label">Hire Date *</label><input type="date" name="hire_date" class="form-control" value="<?=$editData['hire_date']?>" required></div>
    <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?=sanitize($editData['first_name'])?>" required></div>
    <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?=sanitize($editData['last_name'])?>" required></div>
    <div class="form-group full"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?=sanitize($editData['email'])?>" required></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?=sanitize($editData['phone']??'')?>"></div>
    <div class="form-group"><label class="form-label">Department *</label><select name="department_id" class="form-control" required>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=$editData['department_id']==$d['department_id']?'selected':''?>><?=sanitize($d['dept_name'])?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label class="form-label">Position *</label><select name="position" class="form-control">
      <?php foreach($positions as $p): ?><option <?=$editData['position']===$p?'selected':''?>><?=$p?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control">
      <?php foreach(['Active','On Leave','Retired','Terminated'] as $s): ?><option <?=$editData['status']===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  </div></div>
  <div class="modal-footer"><a href="/university/faculty.php" class="btn btn-outline">Cancel</a><button type="submit" class="btn btn-primary">Update Faculty</button></div>
  </form>
</div></div>
<?php endif; ?>

<div class="modal-overlay" id="modal-delete"><div class="confirm-box">
  <div class="confirm-icon">⚠️</div><div class="confirm-title">Remove Faculty?</div>
  <div class="confirm-msg" id="confirm-msg">This will set their status to Terminated.</div>
  <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="faculty_id" id="delete-id">
  <div class="confirm-btns"><button type="button" class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button><button type="submit" class="btn btn-danger">Terminate</button></div>
  </form>
</div></div>

<?php layoutEnd(); ?>