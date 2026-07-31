<h2 class="page-title">Pengaturan Sistem & Tampilan</h2>

<form action="index.php?page=settings&action=system" method="POST" enctype="multipart/form-data">

    <div class="accordion" id="settingsAccordion">
        <!-- General Settings -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    <i class="fas fa-cog me-2 text-primary"></i>Pengaturan Umum & Plant Display
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#settingsAccordion">
                <div class="accordion-body">
                    <div class="mb-3">
                        <label for="app_logo" class="form-label">Logo Aplikasi (untuk Plant Display)</label>
                        <input type="text" class="form-control" id="app_logo" name="app_logo" value="<?php echo htmlspecialchars($settings['app_logo'] ?? ''); ?>" placeholder="contoh: assets/logo.png">
                        <small class="form-text text-muted">Path relatif terhadap folder public.</small>
                    </div>
                    <div class="mb-3">
                        <label for="running_text" class="form-label">Running Text</label>
                        <textarea class="form-control" id="running_text" name="running_text" rows="3"><?php echo htmlspecialchars($settings['running_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="safety_video" class="form-label">Upload Video Safety Induction</label>
                        <input type="file" class="form-control" id="safety_video" name="safety_video" accept="video/mp4,video/quicktime">
                        <small class="form-text text-muted">Upload video MP4/MOV untuk playback lokal di plant display.</small>
                        <?php if (!empty($settings['safety_video_url'])): ?>
                            <div class="mt-2">
                                Video saat ini: <a href="<?php echo htmlspecialchars($settings['safety_video_url']); ?>" target="_blank"><?php echo htmlspecialchars(basename($settings['safety_video_url'])); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="plant_information" class="form-label">Informasi Plant (Tampil di Display)</label>
                        <textarea class="form-control" id="plant_information" name="plant_information" rows="3"><?php echo htmlspecialchars($settings['plant_information'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="base_plant_working_hours" class="form-label">Base Man Hours Without LTI</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="base_plant_working_hours" name="base_plant_working_hours" value="<?php echo htmlspecialchars($settings['base_plant_working_hours'] ?? '0'); ?>">
                            <button class="btn btn-danger" type="submit" name="reset_lti" value="1">Reset LTI</button>
                        </div>
                        <small class="form-text text-muted">Masukkan total jam kerja aman (tanpa LTI) yang akan ditampilkan di Plant Display. Tekan Reset untuk memulai perhitungan dari nilai ini.</small>
                    </div>
                    <hr>
                    <h5 class="mb-3"><i class="fas fa-user-shield me-2 text-success"></i>Pengaturan Petugas On Duty</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="on_duty_name" class="form-label">Nama Petugas</label>
                                <input type="text" class="form-control" id="on_duty_name" name="on_duty_name" value="<?php echo htmlspecialchars($settings['on_duty_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="on_duty_position" class="form-label">Jabatan</label>
                                <input type="text" class="form-control" id="on_duty_position" name="on_duty_position" value="<?php echo htmlspecialchars($settings['on_duty_position'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                             <div class="mb-3">
                                <label for="on_duty_plant" class="form-label">Plant</label>
                                <input type="text" class="form-control" id="on_duty_plant" name="on_duty_plant" value="<?php echo htmlspecialchars($settings['on_duty_plant'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="on_duty_photo" class="form-label">Foto Petugas On Duty</label>
                        <input type="file" class="form-control" id="on_duty_photo" name="on_duty_photo" accept="image/*">
                        <?php if (!empty($settings['on_duty_photo_url'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($settings['on_duty_photo_url']); ?>" alt="Current Officer Image" style="max-height: 80px; border-radius: 4px; background-color: #f0f0f0; padding: 5px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>





        <!-- Plant Color Settings -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    <i class="fas fa-palette me-2 text-warning"></i>Pengaturan Warna Plant (untuk ID Card & Display)
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#settingsAccordion">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plant_color_ca" class="form-label">Warna Plant CA</label>
                                <input type="color" class="form-control form-control-color" id="plant_color_ca" name="plant_color_ca" value="<?php echo htmlspecialchars($settings['plant_color_ca'] ?? '#008000'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plant_color_edc_vcm" class="form-label">Warna Plant EDC/VCM</label>
                                <input type="color" class="form-control form-control-color" id="plant_color_edc_vcm" name="plant_color_edc_vcm" value="<?php echo htmlspecialchars($settings['plant_color_edc_vcm'] ?? '#0000FF'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plant_color_pvc" class="form-label">Warna Plant PVC</label>
                                <input type="color" class="form-control form-control-color" id="plant_color_pvc" name="plant_color_pvc" value="<?php echo htmlspecialchars($settings['plant_color_pvc'] ?? '#FFFF00'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plant_color_mei" class="form-label">Warna Plant MEI</label>
                                <input type="color" class="form-control form-control-color" id="plant_color_mei" name="plant_color_mei" value="<?php echo htmlspecialchars($settings['plant_color_mei'] ?? '#FFA500'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="plant_color_hpi" class="form-label">Warna Plant HPI</label>
                                <input type="color" class="form-control form-control-color" id="plant_color_hpi" name="plant_color_hpi" value="<?php echo htmlspecialchars($settings['plant_color_hpi'] ?? '#800080'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <button type="submit" class="btn btn-primary mt-3">Simpan Semua Pengaturan</button>
</form>
