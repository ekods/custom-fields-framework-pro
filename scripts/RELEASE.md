## Custom Fields Framework Pro 2.5.44

- Give Post Types and Taxonomies dedicated add/edit views using the WordPress post.php layout while retaining their CFF page URLs.
- Put Save definition controls in the right sidebar and use consistent postbox headers, panel spacing and full-width columns; keep list tables out of the editor workspace.
- Refresh CFF navigation, content inventory, field hierarchy and editor controls with blue accents and quieter surfaces.
- Preserve form fields, nonces, translation settings, connected post types, frontend visibility and independent REST settings. Legacy add links remain supported.
- Stack editor columns cleanly on smaller screens and retain native WordPress list-table controls and visible keyboard focus.

Validation: 119 real WordPress add/edit, create/update/cancel, layout and compatibility checks at five viewport widths; 388 runtime-integrity checks; native-table and isolated WordPress builder/save checks.

## Custom Fields Framework Pro 2.5.43

- Align field, repeater, subfield, editor and content-field accents with the teal admin palette.
- Group Conditional Logic and Hide Section inside Advanced options; keep Required visible and saved advanced settings expanded.
- Fix narrow-screen repeater input widths; preserve nested fields and saved field values.
- Distinguish destructive confirmations and import success, warning and failure states.

## Custom Fields Framework Pro 2.5.42

- Refresh admin navigation, headers, cards and editor accents with teal and mint surfaces.
- Use WP_List_Table for content overview, post types and taxonomies, with server-side search, visibility filters, sorting and pagination.
- Restore native Field Groups row actions and table styling; preserve nonce-protected definition actions and responsive row details.

# Custom Fields Framework Pro — build dan rilis

## Folder

| Peran | Lokasi |
|---|---|
| Kerja/edit | `~/Herd/nexamonitor/plugins/custom-fields-framework-pro` |
| Repo commit/rilis | `~/Sites/localhost/ekodwis/wp-content/plugins/custom-fields-framework-pro` |
| Output ZIP | `~/Herd/nexamonitor/dist/custom-fields-framework-pro/custom-fields-framework-pro.zip` |

Alurnya sama dengan Tool Kits. Folder kerja CFF juga memiliki `.git` sendiri;
skrip tidak menghapus atau menyalin `.git`. Untuk publikasi gunakan repo rilis
dengan origin `https://github.com/ekods/custom-fields-framework-pro.git`.

## Build

Jalankan dari folder kerja:

```bash
bash scripts/sync-to-release-repo.sh --dry-run
bash scripts/build-release-zip.sh
```

Build mengecek metadata, menyinkronkan source, mengemas ZIP, dan memvalidasi
hasilnya. Sinkronisasi menggunakan `rsync --delete`: file yang dihapus dari
source ikut dihapus dari repo rilis. Git, konfigurasi lokal, vendor development,
dan ZIP dikecualikan. Aset runtime `assets/vendor/select2` tetap disertakan.

Gunakan preview untuk meninjau perubahan di repo rilis sebelum build. Dependensi:
Bash, sed, grep, rsync, zip, unzip, dan Git untuk sinkronisasi.

Untuk tujuan atau output khusus:

```bash
CFFP_RELEASE_REPO=/path/to/release-repo bash scripts/build-release-zip.sh /tmp/custom-fields-framework-pro.zip
```

Untuk build lokal/CI tanpa menulis ke repo rilis:

```bash
CFFP_SKIP_SYNC=1 bash scripts/build-release-zip.sh
```

Nama folder di dalam ZIP selalu `custom-fields-framework-pro/`, termasuk jika
folder checkout memiliki nama lain. File development dikecualikan. ZIP sebelumnya
diganti hanya setelah hasil build lolos validasi. Folder default juga menyimpan `SHA256SUMS`, `VERSION`, dan `RELEASE_NOTES.md`. Build berikutnya mengganti file tersebut tanpa membuat arsip versi baru. Riwayat rilis tetap berada di GitHub Releases.

Tool Kits memakai metode identik di `dist/tool-kits/`. `RELEASE_SKIP_SYNC=1` berlaku untuk keduanya, dan `RELEASE_DIST_DIR` mengganti direktori output. Checkout mandiri memakai `dist/` di root plugin. Output manual (argumen path ZIP) menghasilkan checksum `<nama-zip>.sha256` di sebelah ZIP.

## Versi dan pengecekan

Sebelum rilis baru, ubah versi yang sama di:

1. Header `Version:` pada `custom-fields-framework-pro.php`.
2. Konstanta `CFFP_VERSION` pada file yang sama.
3. `Stable tag:` pada `readme.txt`.

Tambahkan entri `= VERSION =` pada changelog `readme.txt`, serta catatan rilis
pada `CHANGELOG.md`. Jangan memakai kembali versi/tag rilis yang sudah dipublikasi.

```bash
composer lint
bash scripts/check-release-metadata.sh
bash scripts/check-release-metadata.sh v2.5.18  # setelah versi dinaikkan ke 2.5.18
bash scripts/validate-release-zip.sh
```

Alias Composer: `release:check`, `release:sync`, `release:zip`, `release:validate`.

## Publikasi GitHub

Setelah versi dan changelog diperbarui, jalankan build dari folder kerja.
Review, commit, dan tag dari repo rilis. Contoh untuk versi berikutnya `2.5.18`:

```bash
cd ~/Sites/localhost/ekodwis/wp-content/plugins/custom-fields-framework-pro
git status --short
git diff
git add .
git commit -m "Release 2.5.18"
git push origin HEAD
git tag v2.5.18
git push origin v2.5.18
```

Push tag `v*` menjalankan workflow Release. Workflow menolak tag yang tidak cocok
dengan metadata, melakukan lint PHP, build dan validasi ZIP, lalu membuat GitHub
Release dengan aset bernama `custom-fields-framework-pro.zip`. GitHub Actions
harus aktif dan diizinkan memakai `GITHUB_TOKEN` dengan `contents: write`.

Jika workflow gagal sebelum publikasi, perbaiki penyebabnya dan jalankan ulang
workflow untuk tag tersebut. Jika rilis sudah dipublikasi, gunakan versi baru.

CI pada push dan pull request juga memeriksa lint dan build ZIP.
Setelah publikasi, cek aset di halaman Releases dan uji update dari versi
sebelumnya melalui WordPress. Updater mengambil rilis terbaru dari
`ekods/custom-fields-framework-pro` dan mencari nama aset ZIP tersebut.
