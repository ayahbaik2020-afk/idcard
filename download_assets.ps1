$ErrorActionPreference = 'Stop';
$baseUrl = 'd:\laragon\www\idcard\public\assets';
New-Item -ItemType Directory -Force -Path "$baseUrl\css";
New-Item -ItemType Directory -Force -Path "$baseUrl\js";
New-Item -ItemType Directory -Force -Path "$baseUrl\fonts";
New-Item -ItemType Directory -Force -Path "$baseUrl\webfonts";
New-Item -ItemType Directory -Force -Path "$baseUrl\qr-scanner";

Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" -OutFile "$baseUrl\css\bootstrap.min.css";
Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" -OutFile "$baseUrl\js\bootstrap.bundle.min.js";

Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/chart.js" -OutFile "$baseUrl\js\chart.umd.js";

Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" -OutFile "$baseUrl\css\bootstrap-icons.min.css";
Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2" -OutFile "$baseUrl\fonts\bootstrap-icons.woff2";
Invoke-WebRequest -Uri "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff" -OutFile "$baseUrl\fonts\bootstrap-icons.woff";

Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" -OutFile "$baseUrl\css\all.min.css";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-solid-900.woff2" -OutFile "$baseUrl\webfonts\fa-solid-900.woff2";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-solid-900.woff" -OutFile "$baseUrl\webfonts\fa-solid-900.woff";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-regular-400.woff2" -OutFile "$baseUrl\webfonts\fa-regular-400.woff2";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-regular-400.woff" -OutFile "$baseUrl\webfonts\fa-regular-400.woff";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-brands-400.woff2" -OutFile "$baseUrl\webfonts\fa-brands-400.woff2";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-brands-400.woff" -OutFile "$baseUrl\webfonts\fa-brands-400.woff";

Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.min.js" -OutFile "$baseUrl\qr-scanner\qr-scanner.min.js";
Invoke-WebRequest -Uri "https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner-worker.min.js" -OutFile "$baseUrl\qr-scanner\qr-scanner-worker.min.js";

Write-Output "Done."
