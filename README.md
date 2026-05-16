# ĐỒ ÁN: WEBSITE THƯƠNG MẠI ĐIỆN TỬ ANIMESHOP

## Giới thiệu hệ thống
**AnimeShop** là một nền tảng thương mại điện tử chuyên biệt dành cho những người đam mê văn hóa Anime. Website cung cấp giao diện hiện đại, tối giản (Bespoke Design) tập trung vào trải nghiệm người dùng, giúp khách hàng dễ dàng khám phá và sưu tầm các vật phẩm Anime cao cấp.

## Danh sách thành viên & Phân công nhiệm vụ
| STT | Họ và tên sinh viên | MSSV | Nội dung thực hiện |
|---|---|---|---|
| 1 | Bùi Quang Bình | 23810310010 | Triển khai Website WordPress, cấu hình Database, Deploy lên Hosting, Làm Báo cáo. |
| 2 | Nguyễn Thành Ngọc | 23810310056 | Thiết kế giao diện Website, Xây dựng chức năng, thêm sản phẩm, Làm Báo cáo. |

## Công nghệ sử dụng
*   **Nền tảng**: WordPress (CMS)
*   **Ngôn ngữ chính**: PHP, JavaScript, CSS3, HTML5
*   **Plugin tùy chỉnh**: `anime-shop` (Phát triển riêng cho dự án)
*   **Cơ sở dữ liệu**: MariaDB / MySQL
*   **Tích hợp**: Firebase Real-time Database (Feed đơn hàng)
*   **Công cụ khác**: Ngrok (Tunneling), InfinityFree (Shared Hosting)

## Hướng dẫn cài đặt & Chạy project cục bộ (Local)
1.  Cài đặt môi trường **XAMPP** (PHP 7.4+).
2.  Tải toàn bộ mã nguồn vào thư mục `htdocs/AnimeShop`.
3.  Tạo database mới tên là `anime_shop` trong phpMyAdmin.
4.  Import file `anime_shop.sql` vào database vừa tạo.
5.  Cấu hình thông tin database trong file `wp-config.php`.
6.  Truy cập `http://localhost/AnimeShop` để xem website.

## Hướng dẫn sử dụng tính năng Export/Import
Hệ thống hỗ trợ quản lý sản phẩm thông minh qua file CSV:
*   **Export**: Vào Tools -> Anime Shop Export để xuất toàn bộ sản phẩm (kèm ảnh và thuộc tính).
*   **Import**: Vào trang quản trị Sản phẩm, tải file CSV lên để cập nhật hàng loạt.

## Tài khoản Demo
*   **Admin**: `admin` / `password123`
*   **Customer**: `testuser` / `password123`

## Thông tin Demo
*   **Link video demo**: [Video Link](https://youtube.com/...)
*   **Link online đã deploy**: [https://animeshop.page.gd/](https://animeshop.page.gd/)
