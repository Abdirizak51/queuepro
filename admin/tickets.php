<?php
// admin/tickets.php
require_once __DIR__ . '/../bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_STAFF]);

$pdo       = db();
$pageTitle = 'Tickets';

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action'] ?? '';
    $ticketId = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'update_status' && $ticketId) {
        $newStatus = $_POST['status'] ?? '';
        $allowed   = ['waiting','called','in_progress','completed','cancelled','no_show'];
        if (!in_array($newStatus, $allowed)) { flash('error','Invalid status.'); redirect('tickets.php'); }

        $extra = '';
        $args  = [$newStatus, $ticketId];
        if ($newStatus === 'called')      { $extra = ",called_at=NOW()"; }
        if ($newStatus === 'in_progress') { $extra = ",started_at=NOW(),served_by=?"; array_splice($args,1,0,[$_SESSION['user_id']]); }
        if ($newStatus === 'completed')   { $extra = ",completed_at=NOW()"; }

        $pdo->prepare("UPDATE tickets SET status=?$extra WHERE id=?")->execute($args);

        // Notify user
        $ticket = $pdo->prepare("SELECT * FROM tickets WHERE id=?")->execute([$ticketId]) ? null : null;
        $row    = $pdo->query("SELECT t.*,u.id as uid FROM tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=$ticketId")->fetch();
        if ($row && $row['uid']) {
            $messages = [
                'called'      => ['Your Turn is Near!', "Ticket {$row['ticket_number']}: you have been called. Please come to the counter."],
                'completed'   => ['Service Completed',  "Ticket {$row['ticket_number']} has been completed. Thank you!"],
            ];
            if (isset($messages[$newStatus])) {
                notify($row['uid'], 'ticket_'.$newStatus, ...$messages[$newStatus]);
            }
        }
        logActivity('ticket_status_update', "Ticket #$ticketId → $newStatus");
        jsonResponse(['success' => true, 'status' => $newStatus]);
    }

    if ($action === 'call_next') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $branchId  = (int)($_SESSION['branch_id'] ?? 1);

        $next = $pdo->prepare(
            "SELECT * FROM tickets
             WHERE service_id=? AND branch_id=? AND status='waiting' AND DATE(created_at)=CURDATE()
             ORDER BY priority DESC, created_at ASC LIMIT 1"
        );
        $next->execute([$serviceId, $branchId]);
        $ticket = $next->fetch();

        if (!$ticket) {
            jsonResponse(['success' => false, 'message' => 'No waiting tickets.']);
        }

        $pdo->prepare(
            "UPDATE tickets SET status='called',called_at=NOW(),served_by=? WHERE id=?"
        )->execute([$_SESSION['user_id'], $ticket['id']]);

        if ($ticket['user_id']) {
            notify($ticket['user_id'], 'ticket_called', 'Your Turn!',
                "Ticket {$ticket['ticket_number']}: please come to the counter now.");
        }
        logActivity('ticket_called', "Called ticket #{$ticket['ticket_number']}");
        jsonResponse(['success' => true, 'ticket' => $ticket]);
    }
}

// ---- Fetch ----
$search     = trim($_GET['search']  ?? '');
$status     = trim($_GET['status']  ?? '');
$serviceId  = (int)($_GET['service']?? 0);
$dateFilter = trim($_GET['date']    ?? date('Y-m-d'));
$page       = max(1, (int)($_GET['page'] ?? 1));

$where  = "WHERE DATE(t.created_at) = :date";
$params = [':date' => $dateFilter];
if ($search) {
    $where .= " AND (t.ticket_number LIKE :s OR u.full_name LIKE :s2)";
    $params[':s'] = $params[':s2'] = "%$search%";
}
if ($status) {
    $where .= " AND t.status = :st";
    $params[':st'] = $status;
}
if ($serviceId) {
    $where .= " AND t.service_id = :svc";
    $params[':svc'] = $serviceId;
}

$paged = paginate(
    "SELECT t.*, s.name AS service_name, s.color AS svc_color, s.prefix,
            u.full_name AS customer_name,
            st.full_name AS staff_name
     FROM tickets t
     JOIN services s ON s.id=t.service_id
     LEFT JOIN users u ON u.id=t.user_id
     LEFT JOIN users st ON st.id=t.served_by
     $where ORDER BY t.created_at DESC",
    $params, $page
);

$services = $pdo->query("SELECT id,name,prefix FROM services WHERE status='active'")->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:.2rem">Tickets</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= $paged['total'] ?> tickets on <?= date('d M Y', strtotime($dateFilter)) ?></p>
  </div>
  <div style="display:flex;gap:.7rem">
    <?php if (hasRole(ROLE_STAFF)): ?>
    <button class="btn-brand" data-bs-toggle="modal" data-bs-target="#callNextModal">
      <i class="bi bi-megaphone-fill"></i> Call Next
    </button>
    <?php endif; ?>
    <a href="queue-display.php" class="btn-ghost" target="_blank">
      <i class="bi bi-display"></i> Live Display
    </a>
  </div>
</div>

<!-- Filters -->
<div class="panel" style="margin-bottom:1.25rem">
  <div class="panel-body" style="padding:.85rem 1.25rem">
    <form method="GET" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:center">
      <input type="date" name="date" class="form-control" style="width:160px" value="<?= e($dateFilter) ?>">
      <div style="flex:1;min-width:180px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--muted)"></i>
        <input type="text" name="search" class="form-control" style="padding-left:2.3rem"
               placeholder="Ticket # or customer name" value="<?= e($search) ?>">
      </div>
      <select name="service" class="form-select" style="width:180px">
        <option value="">All Services</option>
        <?php foreach ($services as $sv): ?>
        <option value="<?= $sv['id'] ?>" <?= $serviceId==$sv['id']?'selected':'' ?>><?= e($sv['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select" style="width:160px">
        <option value="">All Statuses</option>
        <?php foreach (['waiting','called','in_progress','completed','cancelled','no_show'] as $st): ?>
        <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-brand"><i class="bi bi-filter"></i> Filter</button>
      <a href="tickets.php" class="btn-ghost"><i class="bi bi-x"></i> Reset</a>
    </form>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ticket</th><th>Customer</th><th>Service</th>
          <th>Status</th><th>Priority</th>
          <th>Called At</th><th>Completed</th><th>Staff</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="tickets-tbody">
        <?php foreach ($paged['data'] as $t): ?>
        <tr id="row-<?= $t['id'] ?>">
          <td>
            <strong style="color:var(--brand);font-size:1rem"><?= e($t['ticket_number']) ?></strong>
            <div style="font-size:.72rem;color:var(--muted)"><?= date('H:i', strtotime($t['created_at'])) ?></div>
          </td>
          <td><?= e($t['customer_name'] ?? 'Walk-in') ?></td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:.4rem">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= e($t['svc_color']) ?>"></span>
              <?= e($t['service_name']) ?>
            </span>
          </td>
          <td id="status-<?= $t['id'] ?>"><?= statusBadge($t['status']) ?></td>
          <td>
            <?php $pc=['normal'=>'bg-secondary','high'=>'bg-warning text-dark','vip'=>'bg-danger']; ?>
            <span class="badge <?= $pc[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span>
          </td>
          <td style="font-size:.8rem;color:var(--muted)">
            <?= $t['called_at'] ? date('H:i', strtotime($t['called_at'])) : '–' ?>
          </td>
          <td style="font-size:.8rem;color:var(--muted)">
            <?= $t['completed_at'] ? date('H:i', strtotime($t['completed_at'])) : '–' ?>
          </td>
          <td style="font-size:.82rem"><?= e($t['staff_name'] ?? '–') ?></td>
          <td>
            <select class="form-select status-change" style="font-size:.8rem;padding:.3rem .6rem;width:130px"
                    data-id="<?= $t['id'] ?>" onchange="changeStatus(this)">
              <?php foreach (['waiting','called','in_progress','completed','cancelled','no_show'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $t['status']===$opt?'selected':'' ?>>
                <?= ucfirst(str_replace('_',' ',$opt)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($paged['data'])): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:3rem">No tickets found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($paged['last_page'] > 1): ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;justify-content:center">
    <?php for ($i=1;$i<=$paged['last_page'];$i++): ?>
    <a href="?page=<?= $i ?>&date=<?= $dateFilter ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&service=<?= $serviceId ?>"
       style="min-width:36px;height:36px;border-radius:8px;display:grid;place-items:center;font-size:.85rem;
              font-weight:600;text-decoration:none;
              <?= $i==$page?'background:var(--brand);color:#fff':'border:1px solid var(--border);color:var(--text)' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- CALL NEXT MODAL -->
<div class="modal fade" id="callNextModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-megaphone"></i> Call Next Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="callResult" style="display:none;text-align:center;padding:1rem"></div>
        <div id="callForm">
          <label class="form-label">Select Service</label>
          <select id="callServiceId" class="form-select mb-3">
            <?php foreach ($services as $sv): ?>
            <option value="<?= $sv['id'] ?>"><?= e($sv['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn-brand" id="callNextBtn" onclick="callNext()">
          <i class="bi bi-megaphone-fill"></i> Call Next
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
async function changeStatus(sel) {
  const id = sel.dataset.id;
  const st = sel.value;
  const res = await fetch("tickets.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},
    body: new URLSearchParams({action:"update_status",ticket_id:id,status:st,_csrf:"'.csrfToken().'"})
  }).then(r=>r.json());
  if (res.success) {
    // Update badge in row
    const cell = document.getElementById("status-"+id);
    // Reload just the cell via page reload (simple approach)
    location.reload();
  } else {
    alert("Error updating status");
  }
}

async function callNext() {
  const svcId = document.getElementById("callServiceId").value;
  const btn   = document.getElementById("callNextBtn");
  btn.disabled = true;
  const res = await fetch("tickets.php", {
    method:"POST",
    headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body: new URLSearchParams({action:"call_next",service_id:svcId,_csrf:"'.csrfToken().'"})
  }).then(r=>r.json());

  const resultDiv = document.getElementById("callResult");
  const formDiv   = document.getElementById("callForm");
  resultDiv.style.display = "block";
  btn.disabled = false;

  if (res.success) {
    resultDiv.innerHTML = `
      <div style="font-size:3rem;color:var(--accent)"><i class="bi bi-megaphone-fill"></i></div>
      <div style="font-size:2.5rem;font-weight:800;color:var(--brand)">${res.ticket.ticket_number}</div>
      <div style="color:var(--muted)">Please come to the counter</div>
    `;
    formDiv.style.display = "none";
    setTimeout(()=>location.reload(), 3000);
  } else {
    resultDiv.innerHTML = `<div style="color:var(--danger)">${res.message}</div>`;
  }
}

// Auto-refresh every 30 seconds
setInterval(() => {
  if (!document.hidden) location.reload();
}, 30000);
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
