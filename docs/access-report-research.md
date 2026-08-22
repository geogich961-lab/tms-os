# Nghiên cứu báo cáo truy cập theo giờ

## Nguồn dữ liệu trong TMS OS

- Panel TMS OS ghi access log tại `~/logs/nginx/tms-access.log`.
- Website mặc định ghi tại `~/logs/nginx/default-access.log`.
- Website do `WebsiteService` tạo ghi riêng tại `~/logs/nginx/<ten-website>-access.log`.
- Không đưa request line thô, URL query, cookie, header xác thực hoặc nội dung log nguyên văn vào báo cáo Telegram.

## IP khách qua Cloudflare

Cloudflare ghi nhận rằng origin thông thường sẽ thấy IP Cloudflare nếu traffic đi qua mạng reverse-proxy của họ. Header `CF-Connecting-IP` mang IP client và chỉ được Cloudflare gửi tới origin cho traffic từ edge. Với Nginx, tài liệu khuyến nghị dùng `ngx_http_realip_module`, đặt `real_ip_header CF-Connecting-IP` và chỉ tin cậy các proxy được khai báo qua `set_real_ip_from`; danh sách IP Cloudflare cần được cập nhật định kỳ.[1][2]

Với TMS OS dùng Cloudflare Tunnel, luồng local đến Nginx thường xuất phát từ proxy cục bộ. Vì vậy, trước khi coi `CF-Connecting-IP` là IP thật, triển khai phải giới hạn proxy tin cậy ở loopback Tunnel, đồng thời từ chối tin header này trên truy cập trực tiếp/LAN để ngăn giả mạo. Nếu header không hiện diện hoặc không đáng tin cậy, báo cáo chỉ dùng địa chỉ nguồn Nginx và gắn nhãn nguồn để người quản trị không nhầm lẫn.

## Chính sách đã chọn

Người dùng chọn chế độ **chi tiết quản trị**: báo cáo mỗi giờ liệt kê IP đầy đủ, số request theo panel/website và mã HTTP đáng chú ý. Giới hạn report theo các IP hoạt động nhiều nhất, giữ state tổng hợp cục bộ tối đa bảy ngày và không sao chép raw log vào Telegram.

## Thiết kế đã chốt

Mỗi lượt chạy chỉ đọc phần log mới thêm kể từ mốc byte đã lưu theo từng file. Khi bật lần đầu, hệ thống chụp mốc cuối file và không gửi lại lịch sử cũ. Nếu log bị cắt hoặc xoay vòng, mốc file được đặt lại an toàn. State chỉ chứa inode, offset, thời gian, số liệu tổng hợp cuối cùng và trạng thái gửi; không chứa token Telegram, URL truy vấn, cookie, request line hay user-agent. File state đặt tại `~/.tms-os/access-report-state.json` với quyền `0600`.

Báo cáo dùng cửa sổ một giờ, tách riêng Panel, Website mặc định và từng website hợp lệ do TMS OS quản lý. Nội dung gồm tổng request, unique IP, số request theo từng đích, lỗi 4xx/5xx và danh sách IP đầy đủ kèm số lượt. Để tránh vượt giới hạn Telegram hoặc tạo bão tin nhắn khi bị quét, danh sách IP được chia tối đa ba tin nhắn, 3.500 ký tự/tin. Nếu vẫn vượt giới hạn này, tin cuối nêu rõ số IP còn lại không được gửi; số tổng và mốc đọc log vẫn được lưu, không gửi raw log thay thế.

Trước khi kích hoạt báo cáo, TMS OS cài cấu hình Nginx chỉ tin `CF-Connecting-IP` khi kết nối tới Nginx đến từ loopback `127.0.0.1` hoặc `::1` của Cloudflare Tunnel. Truy cập LAN/localhost trực tiếp vẫn dùng địa chỉ socket thật và không thể ép Nginx tin header do client tự đặt. Cấu hình được kiểm tra bằng `nginx -t` trước khi reload; nếu mô-đun `realip` không có hoặc kiểm tra lỗi thì dừng kích hoạt thay vì tạo báo cáo IP không đáng tin cậy.

## Điều tra IP loopback qua Tunnel

Kiểm thử thực tế ngày 22/08/2026 cho thấy báo cáo nhận `127.0.0.1`, tức kết nối cục bộ từ `cloudflared`, thay vì IP Internet của khách. Cloudflare xác nhận `CF-Connecting-IP` mang IP khách tới origin cho lưu lượng từ Cloudflare edge; nếu header không có ở origin, cần kiểm tra Transform Rules và Managed Transforms. Cloudflare Tunnel dùng connector `cloudflared` tạo kết nối outbound tới Cloudflare, vì vậy origin có thể chỉ thấy socket local của connector nếu không khôi phục header [2] [3].

Kết luận sau kiểm thử: `CF-Connecting-IP` vẫn là nguồn ưu tiên. Khi header này vắng mặt, TMS OS chỉ dùng IP đầu của `X-Forwarded-For` nếu socket gốc do `cloudflared` trên loopback mở; truy cập LAN trực tiếp không thể kích hoạt fallback. Giá trị cuối cùng còn được kiểm tra lại bằng `FILTER_VALIDATE_IP` trong PHP. Nginx thực đã ghi đúng `198.51.100.42` từ `CF-Connecting-IP` và `203.0.113.77` từ `X-Forwarded-For: 203.0.113.77, 127.0.0.1`.

## Tham chiếu

[1]: https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/ "Cloudflare — Restoring original visitor IPs"
[2]: https://developers.cloudflare.com/fundamentals/reference/http-headers/ "Cloudflare — HTTP headers reference"
[3]: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/ "Cloudflare — Cloudflare Tunnel"
