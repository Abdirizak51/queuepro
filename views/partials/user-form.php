<?php // views/partials/user-form.php ?>
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Full Name *</label>
    <input type="text" name="full_name" class="form-control" placeholder="Ahmed Hassan" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Phone</label>
    <input type="tel" name="phone" class="form-control" placeholder="+252 61 000 0000">
  </div>
  <div class="col-12">
    <label class="form-label">Email Address *</label>
    <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
  </div>
  <div class="col-md-4">
    <label class="form-label">Role</label>
    <select name="role_id" class="form-select">
      <?php
      $allRoles = isset($roles) ? $roles : db()->query("SELECT * FROM roles")->fetchAll();
      foreach ($allRoles as $r):
      ?>
      <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Branch</label>
    <select name="branch_id" class="form-select">
      <?php
      $allBranches = isset($branches) ? $branches : db()->query("SELECT id,name FROM branches WHERE status='active'")->fetchAll();
      foreach ($allBranches as $b):
      ?>
      <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
      <option value="blocked">Blocked</option>
    </select>
  </div>
</div>
