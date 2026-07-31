<h2>Import Kontraktor dari CSV</h2>

<?php if (isset($_SESSION['import_summary'])): ?>
<div class="alert alert-success">
    <p><strong>Import Selesai!</strong></p>
    <ul>
        <li><?php echo $_SESSION['import_summary']['created']; ?> kontraktor baru ditambahkan.</li>
        <li><?php echo $_SESSION['import_summary']['updated']; ?> kontraktor diperbarui.</li>
        <li><?php echo $_SESSION['import_summary']['skipped']; ?> baris dilewati.</li>
    </ul>
</div>
<?php unset($_SESSION['import_summary']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Petunjuk Import</h5>
        <p class="card-text">Pastikan file CSV Anda memiliki format dan header sebagai berikut:</p>
        <pre><code>ID Card,KTP No,photo,Name,Company,Plant Location,Registration Date,Status</code></pre>
        <ul>
            <li>Kolom <strong>ID Card</strong> akan di-generate otomatis jika dikosongkan, tetapi jika diisi akan digunakan untuk update.</li>
            <li>Kolom <strong>KTP No</strong> wajib diisi dan digunakan sebagai unique identifier untuk update data.</li>
            <li>Kolom <strong>photo</strong> harus berisi nama file gambar (misal: <code>1234567890.jpg</code>). Pastikan file gambar sudah diupload ke folder <code>public/uploads/photos/</code>.</li>
            <li>Kolom <strong>Company</strong> akan membuat perusahaan baru jika nama perusahaan belum ada di database.</li>
        </ul>
        <hr>
        <form action="index.php?page=contractors&action=handleImport" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Pilih File CSV</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
            <a href="index.php?page=contractors" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
