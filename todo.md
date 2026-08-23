# Kênh thử nghiệm V16.1.0

- [x] Kiểm tra thay đổi V16.1.0 hiện có, mã nguồn chưa commit và trạng thái nhánh `main`.
- [x] Xác nhận các tệp ứng dụng nhúng, đặc biệt Typecho Việt Hóa, không bị loại khỏi nhánh thử nghiệm.
- [x] Tạo nhánh GitHub riêng `develop-v16.1.0` từ mã nguồn V16.1.0 hiện tại, không tạo GitHub Release.
- [x] Thêm script cập nhật nội bộ có sao lưu an toàn, chỉ tải từ nhánh `develop-v16.1.0`.
- [x] Kiểm tra cú pháp script và xác thực URL raw GitHub của kênh thử nghiệm.
- [x] Chuẩn bị đúng một lệnh Termux để điện thoại cài đặt/cập nhật bản thử nghiệm.
- [x] Tích hợp lệnh quay về mã nguồn trước thử nghiệm khi cần.

## Khắc phục lỗi cập nhật nội bộ

- [x] Kiểm tra logic `start-tms.sh` và điều kiện tiến trình Nginx/PHP trước khi restart.
- [x] Sửa script test để dừng tiến trình đang chiếm cổng trước khi khởi động lại dịch vụ.
- [x] Sửa điều kiện kiểm tra script khởi động sau rollback, không phụ thuộc nhầm vào quyền thực thi.
- [x] Kiểm tra đường khôi phục khi health check trả về HTTP 500.
- [x] Đồng bộ bản sửa restart lên `develop-v16.1.0` mà không tác động `main` hoặc GitHub Release.
- [x] Cung cấp lệnh khôi phục panel an toàn cho điện thoại trước lần test tiếp theo.

## Sửa Cron Jobs và Telegram

- [x] Xác định hàm toast chuẩn được nạp trong layout panel thay cho `tms_toast` không tồn tại.
- [x] Sửa JavaScript lưu Telegram và tất cả hành động Cron để không dừng vì lỗi thông báo.
- [x] Kiểm tra endpoint lưu Telegram không trả về dữ liệu nhạy cảm.
- [ ] Kiểm tra cú pháp view/controller và đồng bộ bản vá lên nhánh `develop-v16.1.0`.
- [ ] Hướng dẫn cập nhật lại kênh test và dùng Bot Token mới an toàn.

## Khắc phục bộ thực thi Cron

- [ ] Kiểm tra cách `CronJobService` tạo crontab và yêu cầu gói `cronie` trên Termux.
- [ ] Kiểm tra script khởi động TMS có kích hoạt dịch vụ cron sau cập nhật hay không.
- [ ] Bổ sung cơ chế khởi tạo `crond` không cần root, có PID file và tránh chạy trùng.
- [ ] Xác nhận wrapper ghi lại trạng thái chạy/thành công/thất bại đúng vào danh sách job.
- [ ] Xác thực cron mẫu mỗi phút trong môi trường Termux tương thích và đồng bộ nhánh test.
- [ ] Cung cấp lệnh kiểm tra dịch vụ cron trên điện thoại sau khi cập nhật.

## Khắc phục Job ID Cron rỗng

- [x] Chặn trường ID rỗng từ form tạo hoặc sửa Cron Job.
- [x] Chuẩn hóa tự động các cron job cũ không có ID hợp lệ.
- [x] Xác nhận dòng crontab luôn truyền Job ID hợp lệ tới `cron-wrapper.php`.
- [ ] Đồng bộ bản vá lên `develop-v16.1.0` và hướng dẫn người dùng kiểm tra lại.

## Xác thực thông báo Telegram của Cron

- [x] Kiểm tra phản hồi Telegram API thay vì chỉ trạng thái lệnh Cron.
- [x] Lưu kết quả giao thông báo an toàn, không lưu hoặc hiển thị Bot Token.
- [x] Hiển thị kết quả gửi Telegram gần nhất trên trang Cron Jobs.
- [ ] Kiểm thử và đồng bộ bản vá lên `develop-v16.1.0`.

## Khắc phục lỗi giao Telegram

- [x] Lấy thông báo lỗi Telegram đã được làm sạch từ tác vụ Cron trên điện thoại.
- [x] Xác định nguyên nhân cấu hình, xác thực hoặc kết nối dựa trên mã lỗi thực tế: Chat ID đang là ID bot.
- [ ] Thay Chat ID bằng ID người dùng hoặc nhóm hợp lệ, không thay Bot Token.
- [ ] Kiểm tra lại trạng thái `Đã gửi` bằng Cron Job trên thiết bị thật.

## Hoàn thiện giao diện Cron Job Manager

- [x] Rà soát layout, màu sắc và thành phần dùng chung của giao diện TMS OS.
- [x] Thiết kế lại phần tổng quan Cron, danh sách tác vụ và trạng thái Telegram dễ quét.
- [x] Tối ưu biểu mẫu tạo/sửa tác vụ và cấu hình Telegram cho màn hình điện thoại.
- [ ] Kiểm tra responsive, cú pháp PHP/JavaScript và đồng bộ lên `develop-v16.1.0`.

## Khắc phục giao diện Cron bị vỡ

- [x] Cô lập nguyên nhân: build `V16.1.0-TEST` không được nhận diện, khiến trình duyệt giữ CSS cũ và SVG phóng đại.
- [x] Buộc làm mới CSS/JavaScript sau mỗi lần cập nhật kênh nội bộ và nhận diện đúng build TEST.
- [ ] Xác thực hiển thị trên desktop và mobile trước khi đẩy lại nhánh thử nghiệm.

## Khôi phục shell chung cho Cron

- [x] Xác minh biến hoặc luồng render khiến Cron bỏ qua layout `os-shell`.
- [x] Khôi phục sidebar, header di động, footer và toast dùng chung cho Cron Jobs.
- [ ] Kiểm tra không phát sinh lỗi menu, toast hoặc dialog trên desktop và mobile trước khi đồng bộ.

## Lệnh trạng thái qua Telegram

- [x] Chọn webhook HTTPS bảo mật để phản hồi lệnh gần như tức thì.
- [x] Thiết kế lệnh `/status`, giới hạn Chat ID và nội dung phản hồi không chứa bí mật.
- [x] Thu thập thông tin thiết bị, tài nguyên và dịch vụ TMS OS có sẵn trên Termux.
- [x] Thêm webhook HTTPS có secret, chống gửi trùng, giới hạn kích thước payload và endpoint quản trị có xác thực/CSRF.
- [x] Thêm kiểm thử service cho secret thiếu/sai, sai Chat ID, lệnh không hỗ trợ, chống trùng và báo cáo an toàn.
- [ ] Kiểm thử bot trả lời trên điện thoại và đồng bộ lên `develop-v16.1.0`.

## Báo cáo truy cập Telegram theo giờ

- [x] Kiểm tra định dạng và vị trí access log Nginx của panel cùng các website do TMS OS quản lý.
- [x] Chọn chế độ chi tiết quản trị: IP đầy đủ, số lượt theo panel/website; loại trừ URL truy vấn, cookie, token và log thô.
- [x] Xác định chính sách riêng tư: tổng hợp IP/đếm truy cập, loại trừ URL truy vấn và không đưa log thô vào Telegram.
- [x] Thiết kế tác vụ theo giờ chạy bằng Cron runtime hiện có, không phụ thuộc tác vụ bên ngoài.
- [x] Tạo dịch vụ tổng hợp, lưu mốc xử lý chống báo trùng và gửi Telegram có xác nhận API.
- [x] Bổ sung điều khiển bật/tắt, gửi thử và trạng thái an toàn vào giao diện Cron Jobs.
- [x] Kiểm thử với access log mô phỏng, cấu hình trusted-proxy, endpoint bị chặn khi chưa đăng nhập và không rò token/URL nhạy cảm.
- [ ] Đồng bộ nhánh thử nghiệm và kiểm thử báo cáo thực tế trên điện thoại.
- [ ] Kiểm thử báo cáo thực tế trên điện thoại trước khi xem xét phát hành ổn định.

## Khôi phục IP khách thật qua Cloudflare Tunnel

- [x] Xác minh header IP mà cloudflared thực tế chuyển vào Nginx và nguyên nhân access log ghi `127.0.0.1`.
- [x] Chỉ chọn header đáng tin cậy từ kết nối loopback của cloudflared, không cho truy cập trực tiếp giả IP.
- [x] Bổ sung tương thích Tunnel trong Nginx, bộ tổng hợp báo cáo và website mới tạo.
- [x] Kiểm thử Nginx thực với `CF-Connecting-IP` và fallback `X-Forwarded-For`.
- [ ] Đồng bộ nhánh thử nghiệm và kiểm thử IP công khai thật, IP LAN trực tiếp trên điện thoại.

## Phát hành ổn định V16.1.0

- [x] Rà soát commits, thay đổi tệp và kết quả kiểm thử trên `develop-v16.1.0`.
- [x] Chuẩn hóa metadata phiên bản từ build TEST sang V16.1.0 ổn định.
- [x] Chạy bộ kiểm thử phát hành, kiểm tra trình cài đặt và luồng cập nhật từ `main`.
- [x] Hợp nhất bản đã xác nhận vào `main`, tạo tag và GitHub Release V16.1.0.
- [x] Xác minh release, asset và hướng dẫn cập nhật một chạm sau phát hành.

## Hotfix bộ cài sau phát hành V16.1.0

- [x] Tái hiện gói source từ `main` và xác định vì sao thư mục `storage` không tồn tại sau giải nén.
- [x] Sửa bộ cài repair để tạo các thư mục runtime bắt buộc mà không đụng dữ liệu hiện có.
- [x] Kiểm thử repair với dữ liệu SQLite, website và cấu hình đã tồn tại.
- [x] Đóng gói, xác minh checksum và phát hành hotfix có hướng dẫn cập nhật an toàn.

## Khắc phục báo cáo Telegram không xác nhận sau V16.1.1

- [ ] Rà soát luồng gửi báo cáo, trạng thái đã làm sạch và dữ liệu cấu hình Telegram được giữ sau repair.
- [ ] Tái hiện phản hồi không thành công bằng transport giả, không gọi Telegram thật.
- [ ] Sửa nguyên nhân và thêm hồi quy cho gửi thử/lịch chạy mỗi giờ.
- [ ] Phát hành bản vá cùng hướng dẫn kiểm tra không cần nhập lại bí mật.

## Khắc phục Nginx chặn báo cáo IP sau V16.1.1

- [x] Tái hiện chính xác lỗi kiểm tra cấu hình Nginx trên luồng repair/Termux.
- [x] Phân biệt lỗi thiếu mô-đun với lỗi cú pháp hoặc vị trí chỉ thị Nginx.
- [x] Sửa migration tương thích mà vẫn chỉ tin header IP từ Cloudflare Tunnel loopback.
- [x] Kiểm thử cấu hình thật, bật báo cáo và gửi Telegram trước khi phát hành hotfix.

## Khắc phục worker Cron báo cáo tự động sau V16.1.2

- [x] Tái hiện lỗi thiếu UnifiedSystemCoreService khi chạy scripts/access-report.php độc lập.
- [x] Sửa thứ tự nạp lớp cho worker mà không ảnh hưởng panel hoặc webhook Telegram.
- [x] Thêm hồi quy bootstrap Cron, lỗi được làm sạch và không lộ đường dẫn hay cấu hình nhạy cảm.
- [x] Kiểm thử package/release và phát hành hotfix có hướng dẫn xác nhận lịch tự động.

## Khắc phục Cloudflare Tunnel 1033 sau repair V16.1.3

- [x] Đối chiếu luồng repair/start-tms với điều kiện khởi động cloudflared và file cấu hình Tunnel.
- [x] Sửa cơ chế phục hồi connector sau cập nhật, không đọc hoặc ghi lộ token/certificate Tunnel.
- [x] Thêm hồi quy trạng thái dịch vụ và hướng dẫn khôi phục tức thời an toàn.
- [x] Kiểm thử package rồi phát hành hotfix nếu lỗi thuộc mã nguồn.

## Khắc phục đổi mật khẩu quản trị làm mất quyền đăng nhập

- [x] Tái hiện đổi mật khẩu và đăng nhập lại bằng kho dữ liệu kiểm thử cô lập.
- [x] Đối chiếu nơi ghi, định dạng hash và nơi xác minh thông tin quản trị.
- [x] Sửa lỗi cùng hồi quy cho mật khẩu cũ/mới và lệnh khôi phục an toàn.
- [x] Kiểm thử package rồi phát hành hotfix có hướng dẫn khôi phục truy cập panel.

## Khắc phục website default hiển thị nhưng không thể xóa

- [x] Tái hiện sự khác biệt giữa website được phát hiện từ Nginx và registry quản lý.
- [x] Sửa thao tác xóa default theo quy tắc an toàn, không tác động website khác.
- [x] Thêm hồi quy cho xóa website default và website có registry bình thường.
- [x] Kiểm thử package rồi phát hành hotfix cùng hướng dẫn thao tác trong panel.

## Khẩn cấp: Cloudflare Hosting trống cấu hình tại V16.1.6

- [x] Xác định tệp cấu hình và đường dẫn lưu API Token, tunnel và hostname Cloudflare.
- [x] Đối chiếu điều kiện giao diện hiển thị token/trạng thái với dữ liệu cấu hình thực tế.
- [x] Xác định cách khôi phục dữ liệu hiển thị mà không tạo hoặc xóa tunnel đang hoạt động.
- [x] Chuẩn bị hướng dẫn kiểm tra an toàn trên điện thoại và phương án sửa lỗi bền vững; phát hành GitHub Release V16.1.7.

## Khẩn cấp: Cloudflare account-info HTTP 400 sau V16.1.7

- [x] Đối chiếu phản hồi `account-info` 400 với trạng thái UI/tunnel/route đã khôi phục.
- [x] Sửa endpoint hoặc xử lý phản hồi lỗi để không làm mất các trạng thái Cloudflare hợp lệ.
- [x] Thêm hồi quy và xác minh không tác động tunnel, domain, Remote Access hoặc cấu hình tối ưu.
- [x] Đóng gói hotfix, phát hành GitHub Release V16.1.8 và chuẩn bị hướng dẫn cập nhật an toàn.

## Hướng dẫn quyền Cloudflare API Token sau V16.1.8

- [x] Đối chiếu lỗi xác thực Zone từ giao diện với các quyền API Token Cloudflare cần có.
- [x] Hướng dẫn tạo token tối thiểu có Zone Read, DNS Edit và Cloudflare Tunnel Edit đúng phạm vi account/zone.
- [x] Hướng dẫn thay token an toàn trong TMS OS và xác minh dropdown Domain mà không thay đổi tunnel hoặc DNS đang hoạt động.

## Khẩn cấp: Token Cloudflare đã lộ và báo Invalid access token

- [x] Hướng dẫn thu hồi token đã lộ và tạo token thay thế với đúng quyền/phạm vi.
- [x] Xác minh token mới trong TMS OS mà không thay đổi tunnel token, route DNS hoặc hostname đang chạy.

## Khẩn cấp: Gắn thc.io.vn vào website cổng 8083 trả HTTP 400

- [x] Truy vết điều kiện backend làm endpoint attach từ chối hostname gốc dù token và Zone đã hợp lệ.
- [x] Sửa an toàn luồng gắn hostname, bảo toàn tunnel, game.thc.io.vn, panel và DNS hiện có.
- [x] Kiểm thử hồi quy attach hostname gốc/subdomain và phát hành hotfix V16.1.9 nếu cần.

## Khẩn cấp: Attach vẫn HTTP 400 sau V16.1.9

- [x] Lấy phản hồi lỗi attach đã được làm sạch từ API thực tế và đối chiếu với mã V16.1.9.
- [x] Sửa điều kiện/định dạng API Cloudflare còn thiếu mà không ghi đè ingress hiện hữu.
- [x] Thêm hồi quy và phát hành hotfix V16.1.10 để xác minh gắn `thc.io.vn` vào cổng 8083.

## Khẩn cấp: Cloudflare từ chối service URL tms-os:8083

- [x] Chuẩn hóa service website nội bộ thành URL loopback có scheme trước khi ghi ingress.
- [x] Thêm hồi quy cho website registry cũ trả về dạng `tên-site:cổng` và URL hợp lệ sẵn có.
- [x] Kiểm thử và phát hành hotfix V16.1.10; chờ người dùng xác minh gắn hostname gốc vào cổng 8083.

## Khẩn cấp: Route chưa hiển thị ngay sau khi Cloudflare nhận ingress

- [x] Truy vết phản hồi PUT/GET cấu hình tunnel và cơ chế đồng bộ route sau khi token đã đủ quyền.
- [x] Xử lý độ trễ hoặc khác biệt phản hồi Cloudflare mà không tự ghi đè ingress, DNS hay hostname cũ.
- [x] Thêm hồi quy, phát hành hotfix V16.1.11 và xác minh ZIP/checksum; chờ xác nhận gắn `thc.io.vn` vào cổng 8083 trên thiết bị.

## Khẩn cấp: Điều khiển Tunnel trả HTTP 502/530

- [x] Truy vết luồng dừng/khởi động Cloudflare Tunnel: panel đang chạy qua chính tunnel sẽ mất đường trả HTTP ngay khi connector bị dừng, dẫn tới 502/530.
- [x] Sửa lỗi điều khiển tunnel: chặn dừng từ panel công khai, cho phép dừng trực tiếp từ localhost/LAN, chặn tạo tunnel trùng và bổ sung đồng bộ route không phá hủy.
- [x] Thêm hồi quy, kiểm thử toàn bộ và phát hành V16.1.12; ZIP/RELEASE.json tải lại từ GitHub có checksum khớp.

## Khẩn cấp: Bộ cài từ chối checksum release hợp lệ

- [x] Đối chiếu checksum nhúng trong `install.sh` với `RELEASE.json` và asset V16.1.12.
- [x] Sửa bộ cài để đọc checksum phát hành hiện hành từ RELEASE.json cùng GitHub Release thay vì giữ checksum tĩnh cũ.
- [x] Kiểm thử cú pháp, toàn bộ hồi quy, manifest/ZIP GitHub Release và install.sh tải lại từ nhánh main trước khi yêu cầu chạy lại.

## Khẩn cấp: Connector Cloudflare chưa kết nối sau cập nhật

- [x] Phân tích: helper đã chủ động che toàn bộ stdout/stderr, vì vậy không báo connector có chạy hay lỗi sau cập nhật.
- [x] Sửa helper để kiểm tra cấu hình/PHP/mã nguồn, báo PID hoặc lỗi khởi động rõ ràng và phát hiện cloudflared thoát sớm mà không làm thay đổi tunnel/DNS/token.
- [ ] Xác nhận lại `panel.thc.io.vn` và các route hoạt động sau khôi phục connector.

## Khẩn cấp: Bật Remote Access làm mất route website gốc

- [x] Tái hiện và xác định vì sao ghi ingress cho `panel.thc.io.vn` làm `thc.io.vn` ngừng phục vụ dù tunnel vẫn healthy.
- [x] Sửa luồng Remote Access để hợp nhất và bảo toàn tất cả ingress hostname hiện có, gồm `thc.io.vn`, `game.thc.io.vn` và `panel.thc.io.vn`.
- [x] Thêm hồi quy, kiểm thử package/release và xác minh ZIP/manifest checksum công khai của V16.1.14.
- [ ] Xác nhận trên thiết bị thật rằng `thc.io.vn`, `game.thc.io.vn` và `panel.thc.io.vn` hoạt động đồng thời sau Repair.

## Khẩn cấp: Asset landing website tại thc.io.vn bị 404

- [ ] Xác định các asset landing đang tham chiếu nhầm `/manus-storage/...` trong môi trường Termux cổng 8083.
- [ ] Đóng gói logo, icon và ảnh hero theo đường dẫn được web server TMS OS phục vụ trực tiếp.
- [ ] Kiểm thử không còn 404 asset/lỗi script trên website sau khi cập nhật, không ảnh hưởng các hostname Cloudflare.

## Trang trạng thái công khai

- [x] Rà soát tuyến public, service Cloudflare và dữ liệu trạng thái có thể công bố an toàn.
- [x] Tạo trang và API trạng thái chỉ đọc cho tunnel cùng các hostname đã quản lý.
- [x] Thêm hồi quy không lộ token/cấu hình riêng, kiểm tra cú pháp/toàn bộ test suite và đóng gói bản cập nhật.
- [x] Phát hành V16.1.15, tải lại ZIP/manifest từ GitHub và xác minh checksum công khai.
- [x] Xác nhận trang `/status` hoạt động trên thiết bị thật sau khi cập nhật.

## Đồng bộ giao diện trang trạng thái

- [x] Đối chiếu header, palette, typography và card trang `/status` với giao diện panel TMS OS hiện hành.
- [x] Thiết kế lại điều hướng/chỉ dẫn công khai và các khối trạng thái để đồng bộ trải nghiệm TMS OS trên điện thoại và tablet.
- [x] Kiểm thử mã PHP/JS, hồi quy dữ liệu API công khai và cache PWA sau khi đồng bộ giao diện.
- [x] Phát hành V16.1.16, tải lại ZIP/RELEASE.json từ GitHub và xác minh checksum cùng asset giao diện.
- [ ] Xác nhận giao diện trang `/status` trên thiết bị sau khi cập nhật.

## Tích hợp trạng thái và đồng bộ App Marketplace

- [x] Tích hợp tóm tắt Tunnel/hostname và liên kết chia sẻ `/status` vào đầu Cloudflare Hosting, không tạo menu/chức năng rời rạc.
- [x] Đồng bộ App Marketplace với shell, header, card, typography, spacing và responsive layout hiện hành của TMS OS.
- [x] Bổ sung hồi quy, kiểm tra cú pháp, toàn bộ test suite và responsive rule mobile/tablet; xác nhận logic cài đặt không thay đổi.
- [x] Phát hành V16.1.17, tải lại ZIP/RELEASE.json từ GitHub và xác minh checksum công khai.
- [x] Xác nhận Cloudflare Hosting và App Marketplace trên thiết bị sau khi cập nhật.

## Khẩn cấp: AdGuard Home báo cài thành công nhưng không hiển thị

- [x] Truy vết trạng thái cài đặt AdGuard Home với service, cổng truy cập và điểm mở ứng dụng trong Marketplace; xác định thiếu xác minh runtime và thiếu điểm hiển thị.
- [x] Sửa luồng cài đặt/khởi động và hiển thị cần thiết, không thay đổi Cloudflare Tunnel, DNS, token hay cấu hình dịch vụ đang chạy.
- [x] Thêm hồi quy và chạy toàn bộ test suite, lint PHP/JS/Bash cùng kiểm tra diff.
- [ ] Phát hành V16.1.18, tải lại ZIP/RELEASE.json để xác minh checksum công khai.
- [ ] Xác nhận AdGuard Home khởi chạy và hiển thị trên thiết bị sau khi cập nhật.
