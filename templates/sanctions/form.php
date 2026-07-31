<?php
$is_edit = isset($sanction);
$form_action = $is_edit ? "index.php?page=sanctions&action=update&id={$sanction['id']}" : "index.php?page=sanctions&action=store";
?>

<h2 class="mb-4"><?php echo $is_edit ? 'Edit Sanksi' : 'Form Input Pelanggaran'; ?></h2>

<form action="<?php echo $form_action; ?>" method="POST">
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label for="contractor_id" class="form-label">Kontraktor</label>
                <select class="form-select" id="contractor_id" name="contractor_id" <?php echo $is_edit ? 'disabled' : 'required'; ?>>
                    <option value="">Pilih Kontraktor...</option>
                    <?php foreach ($contractors as $contractor):
                    ?><option value="<?php echo $contractor['id']; ?>" <?php echo ($is_edit && $sanction['contractor_id'] == $contractor['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($contractor['name'] . ' (' . $contractor['id_card'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="contractor_id" value="<?php echo $sanction['contractor_id']; ?>">
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="violation_id" class="form-label">Jenis Pelanggaran</label>
                <select class="form-select" id="violation_id" name="violation_id" required>
                    <option value="">Pilih Pelanggaran...</option>
                    <?php foreach ($violations as $violation):
                    ?><option value="<?php echo $violation['id']; ?>" <?php echo ($is_edit && $sanction['violation_id'] == $violation['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($violation['name']); ?>
                    </option>
                    <?php endforeach; ?>
                    <option value="new_violation">-- Tambah Jenis Pelanggaran Baru --</option>
                </select>
            </div>
            <div class="mb-3" id="new_violation_container" style="display: none;">
                <label for="new_violation_name" class="form-label">Nama Jenis Pelanggaran Baru</label>
                <input type="text" class="form-control" id="new_violation_name" name="new_violation_name">
            </div>

            <div class="mb-3">
                <label for="sanction_type" class="form-label">Tipe Sanksi</label>
                <select class="form-select" id="sanction_type" name="sanction_type" required>
                    <option value="SP1" <?php echo ($is_edit && $sanction['sanction_type'] == 'SP1') ? 'selected' : ''; ?>>SP1</option>
                    <option value="SP2" <?php echo ($is_edit && $sanction['sanction_type'] == 'SP2') ? 'selected' : ''; ?>>SP2</option>
                    <option value="BANNED" <?php echo ($is_edit && $sanction['sanction_type'] == 'BANNED') ? 'selected' : ''; ?>>BANNED</option>
                </select>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $is_edit ? htmlspecialchars($sanction['start_date']) : date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="end_date" class="form-label">Tanggal Berakhir</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $is_edit ? htmlspecialchars($sanction['end_date']) : ''; ?>">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="is_permanent" name="is_permanent" value="1" <?php echo ($is_edit && $sanction['is_permanent']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_permanent">
                            Banned Permanen
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="reason" class="form-label">Keterangan</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo $is_edit ? htmlspecialchars($sanction['reason']) : ''; ?></textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div>
                <?php if ($is_edit): ?>
                    <a href="index.php?page=sanctions&action=release&id=<?php echo $sanction['id']; ?>" class="btn btn-success" onclick="return confirm('Anda yakin ingin melepaskan sanksi ini?')">Lepaskan Sanksi</a>
                <?php endif; ?>
            </div>
            <div>
                <a href="index.php?page=sanctions" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-danger">Simpan</button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isPermanentCheckbox = document.getElementById('is_permanent');
    const endDateInput = document.getElementById('end_date');

    function toggleEndDate() {
        endDateInput.disabled = isPermanentCheckbox.checked;
        if (isPermanentCheckbox.checked) {
            endDateInput.value = '';
        }
    }

    isPermanentCheckbox.addEventListener('change', toggleEndDate);
    toggleEndDate(); // Initial check on page load

    document.getElementById('violation_id').addEventListener('change', function() {
        var newViolationContainer = document.getElementById('new_violation_container');
        var newViolationNameInput = document.getElementById('new_violation_name');
        if (this.value === 'new_violation') {
            newViolationContainer.style.display = 'block';
            newViolationNameInput.required = true;
            this.required = false;
        } else {
            newViolationContainer.style.display = 'none';
            newViolationNameInput.required = false;
            this.required = true;
        }
    });
});
</script>
