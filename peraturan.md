1. cari terlebih dahulu komponen yang ada di folder Components yang akan digunakan 
2. jika komponen belum ada maka buat komponen tersebut sesuai dengan kebutuhan
3. jika komponen sudah ada maka gunakan komponen tersebut
4. samakan gaya components yang sudah ada
5. samakan gaya penulisan kode dengan file lain
6. selalu berikan komentar pada kode yang kompleks
7. selalu berikan dokumentasi pada setiap perubahan yang dilakukan
8. selalu simpan perubahan yang dilakukan
9. selalu gunakan metode yang memudahkan untuk dibaca dan dipahami
10. selalu gunakan metode yang paling efisien
11. selalu gunakan metode yang paling mudah diuji
12. selalu gunakan metode yang paling mudah di-debug
13. selalu gunakan metode yang paling mudah di-maintenance
14. selalu gunakan metode yang paling mudah di-extend
15. selalu gunakan metode yang paling mudah di-reuse
16. selalu gunakan metode yang paling mudah di-deploy
17. selalu gunakan metode yang paling mudah di-monitor
18. selalu gunakan metode yang paling mudah di-scale
19. selalu gunakan metode yang paling mudah di-test
20. selalu gunakan metode yang paling mudah di-debug
21. pastikan tidak ada bug setelah melakukan perubahan
22. jika ada bug perbaiki bug tersebut sesegera mungkin
23. usahakan menggunakan komponen yang sudah ada untuk menghemat waktu
24. jika membuat route pastikan selalu berikan nama route yang sama pada controller dan view agar mudah diakses dan dilihat
25. samakan gaya font dan ukuran font untuk judul, subjudul, dan isi teks di setiap halamannya
26. jangan menambahkan file komponen jika tidak digunakan
27. pastikan untuk selalu memeriksa halaman atau folder lainnya untuk memastikan jika ada komponen yang mirip atau sama, ada alur data yang mirip atau sama, ada kesamaan atau tidak. minimal memeriksa keseluruhan adalah 2 kali, awal dan akhir
28. jika ada iput gambar maka harus jpg, jpeg dan png saja, tidak menerima format lain
29. selalu memberikan notifikasi atau pemberitahuan kepada pengguna jika terjadi kesalahan atau jika berhasil
30. Selalu lakukan analisis awal secara menyeluruh pada codebase (mencari penggunaan komponen, alur data, atau logic terkait) sebelum mulai menulis kode baru.
31. Selalu jalankan verifikasi build atau compile (seperti `npm run build` atau pengujian terkait) setelah melakukan perubahan untuk memastikan tidak ada error TypeScript atau syntax.
32. Terapkan prinsip self-correction: periksa modifikasi secara mandiri menggunakan `git diff` atau log runtime sebelum menyatakan pengerjaan selesai, agar kesalahan pengetikan atau logic dapat langsung diperbaiki.
33. Jika hasil implementasi tidak sesuai atau memicu error tak terduga, lakukan evaluasi alur data secara bertahap (tracing dari Controller -> Route -> View) atau lakukan rollback untuk mengembalikan kondisi stabil sebelum mencoba solusi lain.
34. Pertahankan konsistensi desain sistem (ukuran border-radius, skema warna HSL/gradient, shadow, serta layout) dengan merujuk pada halaman atau screenshot referensi yang sudah disepakati.
35. Jangan membuat data dummy yang terlalu sederhana; gunakan format data realistis yang menyerupai data produksi agar rendering layout dan pembungkusan kata (text wrap) teruji dengan baik.
36. Ketika memodifikasi database, pastikan seeder dan factory disesuaikan agar lingkungan lokal dapat dijalankan ulang dari awal tanpa merusak integritas data.
37. Selalu periksa performa rendering UI, hindari re-render yang tidak perlu (misal dengan menggunakan computed properties di Vue daripada method untuk data reaktif).
38. Jika membuat form input, pastikan validasi sisi client (frontend) dan sisi server (backend) sinkron agar data yang masuk ke database selalu valid dan aman.
39. Untuk setiap aksi berbahaya (seperti menghapus data permanen), selalu sediakan dialog konfirmasi sebelum aksi dieksekusi demi keamanan data pengguna.
40. Selalu tangani kondisi "empty state" (data kosong) dan "loading state" pada tabel atau halaman daftar agar pengguna tahu status pengambilan data saat ini.
41. Hindari penggunaan "hardcoded" string untuk elemen dinamis; gunakan konfigurasi atau props agar komponen tetap fleksibel dan mudah disesuaikan.
42. Saat menulis query database (SQL atau ORM), pastikan efisiensinya terjaga (misal menggunakan eager loading untuk menghindari masalah N+1 query).
43. Gunakan penamaan variabel, fungsi, dan file yang deskriptif serta konsisten (misal camelCase untuk JavaScript/Vue, snake_case untuk PHP/Database, PascalCase untuk nama Komponen).
44. Pastikan aplikasi responsif di berbagai ukuran layar (mobile, tablet, desktop) dengan menggunakan CSS Grid/Flexbox dan media queries atau utility responsive layout.
45. Jangan pernah mengabaikan peringatan (warning) saat proses build; perbaiki warning tersebut karena seringkali menjadi indikasi awal potensi bug.
46. Terapkan "clean code" dengan memecah fungsi atau komponen yang terlalu besar (>500 baris) menjadi bagian-bagian yang lebih kecil dan fokus pada satu tanggung jawab (Single Responsibility Principle).
47. Saat bekerja dengan asynchronous request (API call), pastikan untuk selalu menangani skenario kegagalan (catch block) dengan memberikan feedback error yang ramah pengguna.
48. Jangan menghapus komentar atau kode dokumentasi yang sudah ada jika tidak berkaitan langsung dengan perubahan yang sedang dilakukan, untuk menjaga riwayat pemikiran pengembang sebelumnya.
49. Pastikan semua elemen interaktif (tombol, link, dropdown) memiliki cursor hover state dan active state yang jelas agar UX terasa hidup dan responsif.
50. Selalu lakukan sinkronisasi branch lokal dengan remote branch secara teratur sebelum mulai mengerjakan tugas baru untuk menghindari conflict kode yang rumit di akhir.