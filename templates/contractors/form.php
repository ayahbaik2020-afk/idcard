<?php
$is_edit = isset($contractor);
$title = $is_edit ? 'Update Data Kontraktor' : 'Daftar Data Kontraktor';
?>

<h2><?php echo $title; ?></h2>

<form id="contractor-form" action="index.php?page=contractors&action=<?php echo $is_edit ? 'update&id=' . $contractor['id'] : 'store'; ?>" method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $is_edit ? htmlspecialchars($contractor['name']) : ''; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="ktp_no" class="form-label">No KTP</label>
                        <input type="text" class="form-control" id="ktp_no" name="ktp_no" value="<?php echo $is_edit ? htmlspecialchars($contractor['ktp_no']) : ''; ?>" required>
                        <div id="ktp-feedback" class="form-text"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="id_card" class="form-label">ID Card</label>
                        <input type="text" class="form-control" id="id_card" name="id_card" value="<?php echo $is_edit ? htmlspecialchars($contractor['id_card']) : '25'; ?>" readonly>
                        <small class="form-text text-muted">ID Card akan di-generate otomatis</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Nama Perusahaan</label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">Pilih Perusahaan</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $is_edit && $contractor['company_id'] == $company['id'] ? 'selected' : ''; ?> > 
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="new_company">-- Tambah Perusahaan Baru --</option>
                        </select>
                    </div>
                    <div class="mb-3" id="new_company_container" style="display: none;">
                        <label for="new_company_name" class="form-label">Nama Perusahaan Baru</label>
                        <input type="text" class="form-control" id="new_company_name" name="new_company_name">
                    </div>
                </div>
            </div>

             <div class="mb-3">
                <label for="photo" class="form-label">Upload Photo</label>
                <input type="file" class="form-control" id="photo" name="photo">
            </div>

        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Photo</label>
                <?php if ($is_edit && !empty($contractor['photo'])):
                    ?><img src="uploads/photos/<?php echo htmlspecialchars($contractor['photo']); ?>" alt="Photo" class="img-fluid rounded">
                <?php else: ?>
                    <img src="assets/images/placeholder-avatar.svg" alt="Placeholder" class="img-fluid rounded">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="mb-3">
                <label class="form-label">Lokasi Plant</label>
                <div class="row">
                    <?php
                    $plants = ['CA PLANT', 'EDC PLANT', 'VCM PLANT', 'PVC PLANT'];
                    foreach ($plants as $plant):
                    ?>
                    <div class="col-12 col-sm-4 col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="plant_location" id="plant_<?php echo str_replace(' ', '_', $plant); ?>" value="<?php echo $plant; ?>" <?php echo $is_edit && $contractor['plant_location'] == $plant ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="plant_<?php echo str_replace(' ', '_', $plant); ?>">
                                <?php echo $plant; ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="registration_date" class="form-label">Tanggal Registrasi</label>
                <input type="date" class="form-control" id="registration_date" name="registration_date" value="<?php echo $is_edit ? htmlspecialchars($contractor['registration_date']) : date('Y-m-d'); ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="expiry_date" class="form-label">Tanggal Expired</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo $is_edit && !empty($contractor['expiry_date']) ? htmlspecialchars($contractor['expiry_date']) : ''; ?>" required>
            </div>
        </div>
    </div>

    <?php if ($is_edit): ?>
    <div class="mb-3">
        <label class="form-label">SANKSI</label>
        <div class="row">
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sanction_type" id="sp1" value="SP1">
                    <label class="form-check-label" for="sp1">SP1</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sanction_type" id="sp2" value="SP2">
                    <label class="form-check-label" for="sp2">SP2</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sanction_type" id="banned_permanent" value="BANNED">
                    <label class="form-check-label" for="banned_permanent">BANNED PERMANEN</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sanction_type" id="banned_days" value="BANNED">
                    <label class="form-check-label" for="banned_days">BANNED SELAMA</label>
                    <input type="number" class="form-control mt-1" name="banned_days" placeholder="Hari" min="1">
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="violation_id" class="form-label">Jenis Pelanggaran</label>
        <select class="form-select" id="violation_id" name="violation_id">
            <option value="">Pilih Pelanggaran</option>
            <?php foreach ($violations as $violation): ?>
            <option value="<?php echo $violation['id']; ?>"><?php echo htmlspecialchars($violation['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="reason" class="form-label">Keterangan</label>
        <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary"><?php echo $is_edit ? 'Update' : 'Daftar'; ?></button>
    <a href="index.php?page=contractors" class="btn btn-secondary">Batal</a>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ktpInput = document.getElementById('ktp_no');
    const ktpFeedback = document.getElementById('ktp-feedback');
    const submitButton = document.querySelector('button[type="submit"]');
    const contractorId = <?php echo $is_edit ? $contractor['id'] : 0; ?>;

    if (ktpInput) {
        ktpInput.addEventListener('blur', function() {
            const ktp_no = this.value.trim();
            if (ktp_no === '') {
                ktpFeedback.textContent = '';
                return;
            }

            ktpFeedback.textContent = 'Checking...';
            ktpFeedback.className = 'form-text text-muted';

            const formData = new FormData();
            formData.append('ktp_no', ktp_no);
            formData.append('contractor_id', contractorId);

            // Try multiple relative paths to avoid 404 depending on how the page is served
            (function tryFetch(urls) {
                let attempt = 0;
                function doAttempt() {
                    if (attempt >= urls.length) {
                        return Promise.reject(new Error('All fetch attempts failed (404)'));
                    }
                    const url = urls[attempt++];
                    return fetch(url, { method: 'POST', body: formData })
                        .then(response => response.text().then(text => ({ url, ok: response.ok, status: response.status, text })))
                        .then(result => {
                            if (result.status === 404) {
                                // try next
                                return doAttempt();
                            }
                            return result;
                        })
                        .catch(err => {
                            // On network error, also try next URL
                            return doAttempt();
                        });
                }
                return doAttempt();
            })(['check_ktp.php', 'public/check_ktp.php'])
            .then(({ ok, status, text }) => {
                if (!ok) {
                    throw new Error('Network response was not ok ' + status);
                }

                // Try parse JSON
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // Not JSON: surface the raw text as an error message
                    throw new Error(text || 'Invalid server response');
                }

                // If server included an 'error' field, show it
                if (data.error) {
                    throw new Error(data.error);
                }

                if (data.exists) {
                    ktpFeedback.textContent = 'Nomer KTP sudah dipakai, pastikan nomer KTP yg anda input asli dan benar...!!';
                    ktpFeedback.className = 'form-text text-danger';
                    submitButton.disabled = true;
                } else {
                    ktpFeedback.textContent = 'No KTP tersedia.';
                    ktpFeedback.className = 'form-text text-success';
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show server-provided error message when possible, else generic message
                const msg = error && error.message ? error.message : 'Terjadi error saat memeriksa KTP. Periksa koneksi dan coba lagi.';
                ktpFeedback.textContent = msg;
                ktpFeedback.className = 'form-text text-danger';
                submitButton.disabled = false; // allow user to submit if they want to proceed
            });
        });
    }

    document.querySelectorAll('input[name="sanction_type"]').forEach(function(el) {
        el.addEventListener('change', function() {
            document.querySelectorAll('input[name="sanction_type"]').forEach(function(other) {
                if (other !== el) other.checked = false;
            });
        });
    });

    const companySelect = document.getElementById('company_id');
    if (companySelect) {
        companySelect.addEventListener('change', function() {
            var newCompanyContainer = document.getElementById('new_company_container');
            var newCompanyNameInput = document.getElementById('new_company_name');
            if (this.value === 'new_company') {
                newCompanyContainer.style.display = 'block';
                newCompanyNameInput.required = true;
            } else {
                newCompanyContainer.style.display = 'none';
                newCompanyNameInput.required = false;
            }
        });
    }
});
</script>