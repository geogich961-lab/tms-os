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
- [x] Phát hành V16.1.18, tải lại ZIP/RELEASE.json để xác minh checksum công khai.
- [ ] Xác nhận AdGuard Home khởi chạy và hiển thị trên thiết bị sau khi cập nhật.

## Hồi quy mới: AdGuard Home chỉ hiện toast, không hiển thị mục đã cài

- [x] Tái hiện lỗi sau POST cài đặt và kiểm tra vì sao dữ liệu `Ứng dụng đang cài` không xuất hiện sau reload.
- [x] Sửa phản hồi/thông báo và render Marketplace để hiển thị rõ trạng thái, cổng và điểm mở AdGuard Home.
- [x] Bổ sung test hồi quy cho thứ tự hiển thị mục ứng dụng đã cài, cache version và chạy lại toàn bộ test suite.
- [x] Phát hành V16.1.19 sau khi xác minh checksum công khai; giữ lại bước người dùng xác nhận thiết bị.

## Lỗi mới: gói AdGuard Home không giải nén trên Android

- [ ] Đối chiếu URL, HTTP response, định dạng archive và asset ARM64 thực tế mà trình cài tải xuống.
- [ ] Kiểm tra tương thích Android/Termux và sửa trình cài để phân biệt archive hỏng, asset sai nền tảng và binary không chạy.
- [ ] Bổ sung hồi quy cho tải xuống/giải nén AdGuard Home; không báo thành công nếu chưa cài và khởi chạy được.
- [ ] Đóng gói/phát hành bản vá sau khi kiểm thử và xác nhận lại trên thiết bị.

## Yêu cầu mới: tạm loại bỏ AdGuard Home

- [x] Xóa AdGuard Home khỏi catalog App Marketplace và các luồng khởi động/khôi phục liên quan.
- [x] Giữ lại tài liệu và lịch sử lỗi để có thể tích hợp lại ở giai đoạn sau; không xóa dữ liệu ứng dụng khác.
- [x] Kiểm thử xác nhận Marketplace, File Browser, WordPress, Typecho và Cloudflare không bị ảnh hưởng.
- [x] Đóng gói và phát hành V16.1.20 với đúng `TMS_OS_LATEST.zip` và `RELEASE.json`.
- [x] Tải trực tiếp asset công khai, đối chiếu checksum `e3edc1722a153728a317fbcca306f88aa53e4a635a76e3b631fa6f5a59ee85f3` và kiểm tra ZIP hợp lệ.
- [ ] Người dùng xác nhận Marketplace trên điện thoại không còn hiển thị AdGuard Home.

## Khẩn cấp: Update Center không áp dụng V16.1.20

- [x] Tái hiện và truy vết vì sao Update Center vẫn giữ V16.1.19 sau khi bấm Cập nhật ngay; xác định ZIP V16.1.20 chứa config V16.1.19.
- [x] Đối chiếu release metadata, tên asset, checksum và bước thay thế mã nguồn/ghi phiên bản trên thiết bị.
- [x] Sửa luồng cập nhật một chạm để chỉ báo thành công sau khi phiên bản thực tế đổi, không xóa dữ liệu hoặc cấu hình Cloudflare.
- [x] Bổ sung hồi quy và phát hành V16.1.21; asset công khai đã được tải trực tiếp, ZIP chứa Platform V16.1.21 và checksum khớp.

## Nâng cấp Update Center: tự kiểm tra và quản lý release

- [ ] Gộp giao diện Update Center thành một luồng gọn, tự kiểm tra phiên bản khi mở trang và ẩn nút cập nhật nếu đang ở bản mới nhất.
- [ ] Hiển thị rõ phiên bản hiện tại, bản mới nhất và danh sách release GitHub để người dùng chọn nâng cấp hoặc hạ cấp.
- [ ] Thêm cảnh báo hạ cấp, yêu cầu xác nhận sao lưu trước thao tác có rủi ro và giữ rollback an toàn.
- [ ] Hiển thị lựa chọn clear cache sau khi nâng/hạ cấp, đồng thời vẫn cho phép người dùng bỏ qua để tự xóa thủ công.
- [ ] Bổ sung test backend/UI, xác minh responsive và kiểm tra không ảnh hưởng dữ liệu, Tunnel, DNS, token hoặc route.

## Khẩn cấp: installer V16.1.21 lỗi Permission denied khi tạo lock/hash và backup

- [x] Tái hiện và xác định đường dẫn/file lock bị từ chối quyền trong chế độ cài mới và Repair.
- [x] Sửa installer để chuẩn hóa quyền thư mục runtime/backup an toàn trước khi tạo lock, không dùng quyền root và không xóa dữ liệu ngoài lựa chọn của người dùng.
- [x] Bổ sung hồi quy cho hash mật khẩu, lock, backup và hai chế độ cài mới/sửa chữa.
- [x] Phát hành V16.1.22 và hướng dẫn người dùng chạy lại an toàn trên thiết bị mới.

## Bổ sung: hash password vẫn thất bại trên Android Termux

- [x] Tách và kiểm tra riêng bước `password_hash`, không gộp với lỗi backup/lock.
- [x] Sửa cơ chế tạo hash để xử lý được môi trường Termux không tạo được file tạm hoặc lock.
- [x] Thêm hồi quy xác nhận hash hợp lệ và tạo được `panel-secret.php` trong cài mới lẫn Repair.

## Khẩn cấp: V16.1.22 ZIP/RELEASE.json không khớp SHA-256 sau khi tải GitHub

- [x] Kiểm tra trạng thái commit, tag, release và toàn bộ asset V16.1.22 trên GitHub.
- [x] Tái hiện bằng URL tag và URL latest, đồng thời kiểm tra redirect/cache và checksum nhiều lần.
- [x] Truy vết quy trình build để loại bỏ khả năng manifest chứa checksum cũ hoặc asset bị thay thế không đồng bộ.
- [x] Sửa quy trình đóng gói/phát hành theo nguyên tắc tạo ZIP một lần, tính checksum một lần và upload manifest tương ứng.
- [x] Xác minh ZIP công khai giải nén được và checksum khớp RELEASE.json trước khi hướng dẫn người dùng cài lại.

## Khẩn cấp: cài mới V16.1.22 vẫn lỗi tạo lock Permission denied

- [x] Xác định chính xác lock file/thư mục và nhánh installer đang được thiết bị tải.
- [x] Mô phỏng quyền thư mục Termux không ghi được và phân biệt lỗi lock với lỗi hash/TMPDIR.
- [x] Sửa installer để lock nằm trong thư mục runtime riêng có quyền ghi, có fallback an toàn và không dùng quyền root.
- [x] Bổ sung hồi quy riêng cho cài mới, Repair và trường hợp chạy installer từ thư mục không ghi được.
- [x] Đóng gói, phát hành và xác minh asset công khai trước khi yêu cầu người dùng chạy lại.

## Khẩn cấp: V16.1.23 vẫn không cài được trên thiết bị mới

- [x] Thu thập toàn bộ log quanh dòng lỗi và xác định installer/phiên bản thực tế đang chạy.
- [x] Phân biệt lock của apt/pkg với lock `.tms-os` hoặc lock do tiến trình dịch vụ tạo.
- [x] Kiểm tra quyền sở hữu/quyền ghi của `$HOME`, `$PREFIX` và các đường dẫn lock trên Termux.
- [x] Chỉ sửa và phát hành bản mới sau khi tái hiện đúng nguyên nhân, có test hồi quy tương ứng.
- [x] Xác nhận quyền bộ nhớ dùng chung đã cấp nhưng lỗi lock vẫn tái diễn; không coi `termux-setup-storage` là biện pháp sửa lock nội bộ.

## Khẩn cấp: lỗi lock tại bước tạo tài khoản quản trị trên Android cũ

- [x] Đối chiếu dòng 454 trong installer với lệnh thực tế gây `Permission denied (13)`.
- [x] Kiểm tra riêng đường dẫn backup, file hash, file secret và lock/session của PHP tại bước [5/7].
- [x] Sửa cơ chế tạo tài khoản để không phụ thuộc file tạm hoặc thư mục không ghi được.
- [x] Bổ sung hồi quy cho SQLite + tài khoản quản trị trên môi trường quyền hạn chế.

## Khẩn cấp: PHP Engine không khởi động ở bước [6/7]

- [x] Xác định lệnh khởi động PHP thực tế, binary được dùng và file cấu hình PHP/CGI-FPM.
- [x] Thu thập log PHP, PID, cổng và lỗi cấu hình trên môi trường Android/Termux cũ.
- [x] Sửa cơ chế khởi động có fallback tương thích, không báo thành công giả.
- [x] Bổ sung hồi quy cho PHP startup, nginx upstream và trạng thái dịch vụ sau cài mới.

## Khẩn cấp: thiết bị vẫn chạy mã cũ tại dòng 454 sau V16.1.25

- [ ] Tải ZIP tag và latest, kiểm tra nội dung `scripts/install.sh` thực tế bên trong.
- [ ] Đối chiếu dòng ghi `panel-secret.php` và xác nhận có `php -n` trong asset public.
- [ ] Kiểm tra installer một dòng trên `main` có trỏ đúng asset/version hay không.
- [ ] Nếu asset sai, thay bằng ZIP được build lại từ commit chính xác rồi xác minh checksum trước khi yêu cầu chạy lại.

## Khẩn cấp: V16.1.25 vượt checksum nhưng PHP Engine vẫn thất bại ở [6/7]

- [ ] Đọc log `~/logs/services/php-engine.log` và xác định mã lỗi runtime thực tế trên thiết bị.
- [ ] Đối chiếu lệnh PHP-FPM/CGI, PID, cổng 9000 và quyền ghi log/socket.
- [ ] Sửa engine để không báo thất bại giả và tương thích Android/Termux cũ.
- [ ] Bổ sung test hồi quy cho trạng thái process, port và nginx upstream.
- [ ] Chỉ phát hành bản tiếp theo sau khi xác minh lỗi bằng log và test thực tế.

## Khẩn cấp: PHP Engine lock nội bộ bị Permission denied

- [ ] Đối chiếu thông báo trong `php-engine.log` với toàn bộ lệnh tạo lock của `tms-php-engine.sh`.
- [ ] Xác định đường dẫn lock thực tế, quyền sở hữu và quyền ghi của thư mục cha trên Termux.
- [ ] Kiểm tra lock cũ có bị tạo bởi phiên bản trước hoặc tiến trình khác hay không.
- [ ] Sửa lock để dùng file trong runtime có quyền ghi, dọn lock stale an toàn và không dùng `chmod -R 777`.
- [ ] Bổ sung hồi quy cho FPM/CGI, lock stale, restart và cài mới.
- [ ] Chỉ tạo release mới sau khi ZIP, manifest và nội dung engine được kiểm tra đồng nhất.

### Kết quả điều tra PHP Engine ngày 2026-08-24

- [x] Log người dùng xác nhận lỗi lặp lại là `Cannot create lock - Permission denied (13)` ngay khi PHP Engine khởi động; không phải lỗi checksum hay quyền bộ nhớ Android.
- [x] Đối chiếu source cho thấy wrapper gọi `php-fpm` hệ thống trước, còn fallback gọi `php-cgi` nhưng vẫn nạp cấu hình PHP mặc định.
- [x] Sửa fallback thành `php-cgi -n -b 127.0.0.1:9000`, đồng thời dọn cả biến thể CGI cũ/mới khi stop hoặc restart.
- [x] Chạy `bash -n` cho toàn bộ script, toàn bộ test PHP trong `tests/*_test.php`, và `git diff --check`; tất cả đạt.
- [ ] Chưa phát hành bản mới; cần đóng gói từ commit sửa này và xác minh nội dung ZIP cùng checksum trước khi đưa người dùng chạy lại.

- [x] Đóng gói V16.1.26 từ commit d620d1176f1bc7c616c0bfa7e3843d48cec57ebe, tính checksum duy nhất và cập nhật manifest.
- [x] Phát hành asset đúng tên `TMS_OS_LATEST.zip` cùng `RELEASE.json` trên GitHub.
- [x] Tải trực tiếp theo URL tag và latest, xác nhận SHA-256 cùng là `d6e8dc0c0d412cdfdd3ff3ca58bbc4167a7e119cda1410df9c7758ceaf302e43`, ZIP giải nén đạt.

## Blocker mới: PHP Engine vẫn thoát trên V16.1.26

- [ ] Thu thập `php-engine.log` ngay sau lần cài sạch V16.1.26 thất bại.
- [ ] Xác định binary thực tế (`php`, `php-cgi`, `php-fpm`), tiến trình, cổng 9000 và đường dẫn PID/lock trên thiết bị.
- [ ] Kiểm tra trực tiếp lệnh PHP-CGI sạch cấu hình và phản hồi TCP trước khi sửa engine.
- [ ] Đối chiếu cấu hình nginx upstream với chế độ PHP thực tế; không phát hành bản mới khi chưa tái hiện được lỗi runtime.

## Bằng chứng mới: PHP binary tự lỗi lock

- [ ] Đối chiếu `php` và `php-cgi` thực tế là binary hay wrapper/script trên Termux.
- [ ] Tìm toàn bộ đường dẫn lock/session/cache mà PHP hoặc package Termux có thể dùng; không suy đoán từ nginx.
- [ ] Kiểm tra quyền và mount của `$HOME`, `$PREFIX`, `$TMPDIR`, `~/.cache` và các thư mục PHP liên quan.
- [ ] Xác định có cần sửa package PHP/Termux trên thiết bị hay chỉ cần thay biến môi trường runtime.
- [ ] Chỉ cập nhật TMS Engine sau khi phép thử `php-cgi -n` độc lập chạy thành công.

## Blocker môi trường Termux/PHP 8.5.1

- [ ] Xác minh PHP 8.5.1 lỗi độc lập với TMS bằng `php -n -r` và `php-cgi -n`.
- [ ] Kiểm tra package, wrapper, file cấu hình và nguồn lock của PHP/Termux; không kết luận chỉ từ quyền thư mục.
- [ ] Xây dựng cách sửa package PHP an toàn, không xóa dữ liệu TMS hoặc dùng `sudo`/`chmod -R 777`.
- [ ] Thêm preflight vào installer để dừng sớm với hướng dẫn rõ nếu PHP native không tạo lock được.
- [ ] Chỉ phát hành bản TMS mới sau khi `php -n -r` và `php-cgi -n` trên môi trường kiểm thử đều thành công.

## Phát hiện gốc: Termux thiếu `$PREFIX/var/tmp`

- [x] Thêm khởi tạo `$PREFIX/var/tmp` trước mọi lệnh PHP/engine và đặt quyền tối thiểu an toàn.
- [x] Ép PHP Engine dùng `$PREFIX/tmp` hoặc runtime temp riêng có thể ghi, không phụ thuộc thư mục bị thiếu.
- [x] Bổ sung kiểm tra `mktemp` và thông báo lỗi phân biệt `No such file or directory` với `Permission denied`.
- [x] Kiểm thử trực tiếp `php -n`, `php-cgi -n` và khởi động engine trên môi trường thiếu `var/tmp`.
- [ ] Chỉ phát hành bản mới sau khi PHP độc lập và toàn bộ installer đạt.

## Blocker mới: panel chưa lắng nghe cổng 8888 sau khi sửa temp

- [ ] Thu thập trạng thái `php-cgi`, `php-fpm`, Nginx và log khởi động trên thiết bị thật.
- [ ] Xác định lệnh `php-cgi` có hỗ trợ tham số khởi động hiện tại và có tạo upstream cổng 9000 không.
- [ ] Sửa luồng `start-tms.sh`/PHP Engine nếu dịch vụ chưa khởi động sau preflight temp.
- [ ] Kiểm thử lại endpoint `127.0.0.1:8888/login` trước khi phát hành V16.1.27.

## 502 sau khi Nginx hợp lệ: PHP upstream không chạy

- [ ] Thu thập exit code và stderr trực tiếp của `php-cgi -n -b 127.0.0.1:9000`, không che lỗi bằng `|| true`.
- [ ] Xác định PHP đang chạy FPM hay CGI và file/đường dẫn lock thực tế từ log.
- [ ] Phân biệt lỗi lock của PHP native với lỗi netlink khi lấy thông tin mạng.
- [ ] Sửa hoặc đưa ra hướng package reinstall an toàn, sau đó xác nhận upstream cổng 9000 và panel HTTP 200.

## Kết luận native PHP-CGI bị lỗi lock

- [ ] Kiểm tra phiên bản package `php`, `php-cgi`, nguồn repo và danh sách file package trên thiết bị.
- [ ] Kiểm tra các biến môi trường Termux có thể ảnh hưởng tới lock, gồm `TMPDIR`, `PREFIX`, `HOME`, `PHP_INI_SCAN_DIR`.
- [ ] Thử cài lại riêng package PHP theo cách không xóa dữ liệu TMS, chỉ sau khi lưu thông tin phiên bản/package.
- [ ] Thêm preflight `php -n -r` và `php-cgi -n` vào installer để dừng sớm với hướng dẫn package rõ ràng.
- [ ] Chỉ phát hành V16.1.27 khi PHP-CGI native chạy được và endpoint panel không còn 502.

## Phân tích package PHP 8.5.1

- [x] Xác nhận `php`/`php-cgi` cùng thuộc package PHP 8.5.1 từ Termux stable/main aarch64.
- [x] Xác nhận `$PREFIX/var/tmp`, `$PREFIX/var/run` và `$HOME/.tms-os/tmp` đã tồn tại sau preflight.
- [x] Xác nhận PHP CLI `php -n -r` chạy được nhưng FastCGI `php-cgi -b` vẫn thoát exit 255 với lỗi lock.
- [ ] Dùng strace để xác định đường dẫn hoặc syscall gây lỗi trong FastCGI.

## Manh mối LD_PRELOAD/termux-exec

- [ ] So sánh `php-cgi -b` với môi trường mặc định và `env -u LD_PRELOAD`.
- [ ] Đọc nội dung log strace đầy đủ nếu syscall trace đã được tạo.
- [ ] Kiểm tra riêng `99-tms-os.ini` bằng PHP-CGI không dùng cấu hình, rồi mới cân nhắc sửa wrapper.
- [ ] Chỉ vô hiệu hóa preload trong tiến trình PHP nếu thử nghiệm xác nhận nguyên nhân, không sửa profile Termux toàn cục.

## Tái thiết kế bộ cài tương thích nhiều thiết bị

- [ ] Xác định ma trận hỗ trợ theo Android API, kiến trúc CPU, nguồn Termux và phiên bản PHP.
- [x] Tách installer thành các giai đoạn preflight, download/verify, backup, install, migrate, health-check và commit.
- [x] Thiết kế rollback an toàn, giữ nguyên website/database/config khi repair thất bại.
- [ ] Xây dựng lựa chọn engine theo kết quả kiểm thử thực tế, không giả định PHP-CGI/FPM luôn hoạt động.
- [x] Tạo báo cáo chẩn đoán một lần để người dùng gửi lại, tránh chạy nhiều lệnh lặp.
- [ ] Kiểm thử trên profile Android/Termux mô phỏng và tối thiểu một thiết bị thật trước khi phát hành.

## Triển khai installer v2: preflight và rollback

- [x] Viết thư viện shell dùng chung cho report, kiểm tra điều kiện và mã lỗi preflight.
- [x] Viết manifest/staging state để rollback theo từng phiên cài.
- [x] Tích hợp preflight vào root installer trước download/ghi dữ liệu.
- [x] Tích hợp backup, staging và rollback vào sub-installer ở chế độ repair/install.
- [x] Bổ sung test shell cho thiếu temp, PHP-CGI fail, Nginx fail, checksum fail và rollback giữ dữ liệu.
- [x] Chạy bash syntax, shell regression, kiểm tra package sạch và cập nhật hướng dẫn test Termux.

## Kiểm thử installer trên Termux thật

- [ ] Chạy `tests/installer_safety_test.sh` trực tiếp trên thiết bị Termux.
- [ ] Chạy probe PHP-CGI native riêng, ghi exit code và stderr không che lỗi.
- [ ] Lưu báo cáo test một file để phân tích, không xóa dữ liệu TMS OS.
- [ ] Phân loại kết quả và quyết định sửa wrapper hay repair package PHP.

## Kiểm thử trên thiết bị Android mới hoàn toàn

- [ ] Cài Termux từ một nguồn duy nhất và xác nhận phiên bản Android/ABI.
- [x] Chạy kiểm tra môi trường không cài TMSản trước khi cài TMS OS.
- [x] Cài riêng package PHP trên thiết bị mới và kiểm tra `php -n` cùng `php-cgi -n -b`.
- [ ] Gửi báo cáo thiết bị để phân loại tương thích trước khi cài TMS OS.
- [ ] Chỉ chạy installer TMS OS sau khi preflight đạt hoặc người dùng xác nhận profile giới hạn.

## PHP 8.5.1 thất bại ở mọi server mode

- [x] Xác nhận `php -S` cũng thoát 255 với `Cannot create lock`, cả có và không có `LD_PRELOAD`.
- [x] Đặt PHP 8.5.1 server mode vào trạng thái không tương thích, không tiếp tục dùng fallback FPM/CGI/built-in.
- [ ] Bổ sung compatibility gate kiểm tra `php -S` hoặc probe server tương đương trước khi tạo cấu hình Nginx.
- [ ] Nghiên cứu package/version PHP thay thế hoặc kiến trúc runtime không phụ thuộc PHP server mode.
- [ ] Không phát hành V16.1.27 cho đến khi có backend PHP chạy thật trên Termux.

## Universal Compatibility Installer

- [ ] Chuẩn hóa capability profile: Android API, ABI, nguồn Termux, dung lượng, quyền ghi, loopback, battery/background và package manager.
- [ ] Xây dựng dependency planner phân biệt dependency bắt buộc, tùy chọn và không tương thích.
- [ ] Tạo preflight report cho biết thiết bị phù hợp với profile nào, còn thiếu gì và lý do từ chối nếu không thể chạy.
- [ ] Tự cài dependency còn thiếu theo từng transaction, xác minh sau mỗi package và không dùng nguồn không được người dùng chấp thuận.
- [ ] Bổ sung health gate cho PHP CLI, PHP server mode, Nginx, SQLite/MariaDB và endpoint panel trước activate.
- [ ] Tích hợp staging, backup, commit và rollback xuyên suốt toàn bộ dependency/install transaction.
- [ ] Thêm chế độ `--diagnose`, `--plan`, `--install`, `--repair` và `--rollback` cho installer một dòng.
- [ ] Kiểm thử nhiều profile Android/Termux, đặc biệt profile PHP CLI đạt nhưng server mode thất bại.
- [ ] Cập nhật tài liệu cài đặt và chỉ phát hành sau khi test trên thiết bị thật đạt.
- [x] Xóa toàn bộ GitHub Releases ngoại trừ V16.1.21 và xác minh tag/release mục tiêu còn nguyên
- [x] Tạo và xác minh backup độc lập của mã nguồn đúng release v16.1.21 trước khi tiếp tục phát triển
- [x] Kiểm thử ma trận Android API/ABI cho v16.1.21 và xác định mức tối thiểu cài đặt thành công
- [x] Tích hợp Universal Compatibility Installer vào payload V16.1.21, đóng gói và cập nhật asset release có checksum mới
- [x] Sửa test matrix live để kỳ vọng mã lỗi 10 cho Android API 23 và chạy lại toàn bộ UCI matrix
- [x] Tạo tập lệnh tự động kiểm tra integrity payload UCI trên nhiều Android API/ABI, có báo cáo và mã thoát rõ ràng
- [x] Thiết lập GitHub Actions chạy verify-uci-payload.sh khi có build mới và chặn phát hành khi kiểm tra thất bại
- [x] Tạo gói pilot Android thật gồm collector log an toàn, checklist cài đặt/repair/rollback và hướng dẫn thử nghiệm
- [x] Soạn hướng dẫn cài đặt TMS OS UCI từng lệnh cho Sony Xperia XZ Premium chạy Android 7
- [x] Khắc phục tải payload GitHub bị curl 56 trên Android 7 bằng retry, IPv4 fallback và kiểm tra checksum
- [x] Khắc phục lỗi `home: unbound variable` trong installer safety preflight trên Android 7
- [x] Đóng gói lại và thay asset payload v16.1.21 cùng RELEASE.json để phát hành bản vá safety preflight
- [x] Chặn installer tiếp tục, bật auto-start và báo thành công khi người dùng hủy xác nhận Cài mới
- [x] Khắc phục PHP engine không khởi động và 502 Bad Gateway trên Xperia Android 7 sau cài mới
- [x] Đồng bộ nút Start/Restart PHP Engine trong Service Manager với launcher runtime đã kiểm thử
- [ ] Khắc phục cài đặt và phát hiện Redis tùy chọn trên Xperia Android 7
- [x] Kiểm tra chỉ đọc trạng thái dịch vụ Xperia: tiến trình, cổng, phản hồi panel/PHP/Redis và auto-start; phát hiện thiếu Termux:Boot cùng Redis chưa được khôi phục sau reboot.
- [x] Hoàn thiện auto-start Xperia: yêu cầu Termux:Boot rõ ràng và khôi phục Redis tùy chọn sau reboot mà không ảnh hưởng PHP/Nginx/SQLite.
- [x] Chuẩn bị TMS OS V17.0.0 ổn định từ nền V16.1.21 với auto-start Termux:Boot và Redis tùy chọn sau reboot.
- [x] Đóng gói, kiểm thử integrity và phát hành GitHub Release V17.0.0.
- [x] Xóa các GitHub Release cũ sau khi V17.0.0 được xác minh, chỉ giữ V16.1.21 và V17.0.0.
- [x] Khắc phục Update Center V17.0.0 trên Xperia: bước xác nhận sau cập nhật phải luôn trả JSON hợp lệ thay vì HTML.
- [x] Khôi phục cập nhật một chạm trực tiếp từ Update Center trên panel V17 cũ: trả JSON trước restart và tự xác minh sau restart, không cần lệnh Termux.
- [x] Phát hành V17.0.1 hotfix để panel V17.0.0 nhận và áp dụng cập nhật trực tiếp; sau xác minh chỉ giữ V16.1.21 và V17.0.1.
- [x] Khắc phục Runtime Package Cloudflare Tunnel V17.0.1 treo request/panel; chuyển cài đặt sang nền có timeout, trạng thái rõ ràng và không ảnh hưởng PHP/Nginx/SQLite.
- [x] Phát hành V17.0.2 để TMS OS V17.0.1 nhận hotfix Runtime Package Cloudflare Tunnel trực tiếp từ Update Center.
- [x] Khắc phục Update Center trên Xperia: V17.0.2 được phát hiện/tải nhưng bước xác minh vẫn báo V17.0.1, bảo đảm cập nhật một chạm thực sự áp dụng payload trước khi thông báo thành công.
- [x] Khắc phục Update Center Xperia bị kẹt ở “Đang xác minh phiên bản (12/12)” hoặc “cập nhật trong hàng đợi” sau khi source đã đổi version; hoàn tất idempotent, có timeout và tự dọn job cũ.
- [ ] Tái hiện và sửa đường nâng trực tiếp từ source V17.0.2 trên Xperia: worker phải áp dụng đúng payload mới, không kẹt ở xác minh/hàng đợi và không phát hành thêm trước khi kiểm chứng tương thích ngược.
- [ ] Ổn định Xperia đang chạy V17.0.3: xử lý lỗi Update Center không kết nối GitHub và đồng bộ release/giao diện để không đề xuất cập nhật mâu thuẫn.
- [ ] Khôi phục truy cập công khai thc.io.vn và taone.thc.io.vn: hoàn tất Cloudflared cài nền, khởi động tunnel an toàn và hiển thị trạng thái tunnel thực tế thay vì chỉ trạng thái DNS/hostname.
- [x] Đối chiếu chỉ đọc tunnel Cloudflare, CNAME của thc.io.vn/taone.thc.io.vn và connector trên Xperia để xác định nguyên nhân Error 1033 mà không thay đổi DNS, ingress hoặc token.
- [x] Xác định nguyên nhân Cloudflared đã cài nhưng tiến trình connector không duy trì replica trên tunnel; chỉ dùng thông báo lỗi đã làm sạch, không để lộ token hay yêu cầu đổi token.
- [ ] Khôi phục phân giải DNS cho Cloudflared trên Xperia: lỗi SRV lookup qua 8.8.4.4 bị timeout làm connector tự dừng; không thay tunnel, token, ingress, CNAME hay dữ liệu TMS OS.
- [x] Thay thế phương án DNS-only VPN không tương thích bằng resolver cục bộ cho cloudflared/Termux, vì VPN 1.1.1.1 không đổi resolver của connector và làm Xperia mất Internet.
- [x] Xử lý tương thích Wi‑Fi công cộng: hotspot đã xác nhận tunnel tạo bình thường 4 kết nối, cần cơ chế tránh DNS UDP timeout riêng cho connector mà không thay DNS Android hoặc Cloudflare.
- [x] Bổ sung chế độ opt-in “Tương thích Wi‑Fi công cộng”: sao lưu, áp dụng và hoàn tác resolver riêng của Termux khi khởi động cloudflared; không thay DNS Android, tunnel, routes hoặc token.
- [x] Đóng gói và phát hành GitHub hotfix V17.0.4 cho Cloudflare resolver, kiểm tra đường nâng từ V17.0.3 qua Update Center và SHA-256 trước khi Xperia cài thử.
- [ ] Xác minh V17.0.4 trên Xperia tại Wi‑Fi Highland: chỉ xác nhận khắc phục sau khi tunnel lên Active và hostname truy cập được; dừng thử nghiệm nếu mạng chặn DNS công khai hoặc Cloudflare Tunnel ở tầng mạng.
- [x] Điều tra và khôi phục riêng lỗi Cloudflare 504 tại `/updates`: xác nhận trạng thái origin, PHP/Nginx và job cập nhật sau khi phiên đã hết hạn; không bấm cập nhật lặp lại hoặc thay đổi dữ liệu/tunnel trước khi có chẩn đoán.
- [x] Khẩn cấp khôi phục panel local Xperia sau khi bấm Cập nhật nhanh: `127.0.0.1:8888/updates` cũng không phản hồi, cần kiểm tra process/port và trạng thái worker không phá hủy trước khi thử thêm cập nhật.
- [x] Khắc phục triệt để Update Center: sau khi swap source phải khởi động lại dịch vụ bằng cơ chế độc lập, có thời gian chờ/health confirmation và không để panel local mất phản hồi nếu bước restart lỗi.
- [x] Loại bỏ hoàn toàn App Marketplace để tối giản TMS OS: menu, route, controller, service, view, JavaScript, CSS/tài nguyên và liên kết liên quan; không gỡ hoặc can thiệp dịch vụ/website đã cài đặt trước đó.
- [x] Đóng gói và phát hành V17.0.5 từ payload V17.0.4, gồm bootstrap repair cho thiết bị đang ở V17.0.4; xác minh SHA-256, đường nâng, restart health và bảo toàn dữ liệu trước khi Xperia cập nhật.
- [ ] Xác minh thực địa V17.0.5 trên Xperia sau bootstrap repair: Update Center chỉ xác nhận hoàn tất khi panel local phản hồi, sau đó mới thử tùy chọn tương thích Wi-Fi Cloudflare tại Highland.
- [x] Sửa cảnh báo sai sau cập nhật: khi polling nhận `AUTH_REQUIRED` vì phiên cũ bị mất sau restart nhưng version đã đổi, yêu cầu đăng nhập lại rõ ràng thay vì thông báo “Chưa xác nhận cập nhật”.
- [x] Đóng gói và phát hành V17.0.6 từ payload V17.0.5: xác minh SHA-256, đường nâng, giữ phiên sau restart và xử lý re-authentication trước khi Xperia cài.
- [ ] Xác minh thực địa V17.0.6 trên Xperia: Update Center giữ phiên sau restart; nếu hết hạn thực sự, giao diện yêu cầu đăng nhập lại thay vì báo cập nhật chưa được xác nhận.
- [x] Khắc phục trang `/login` hiển thị HTML thô sau re-authentication: luôn nạp layout header/footer và CSS/JS, không làm đổi luồng xác thực hoặc chuyển hướng nội bộ an toàn.
- [x] Đóng gói, phát hành và xác minh V17.0.7: sửa giao diện login sau Update Center, làm mới cache PWA và giữ nguyên dữ liệu/cấu hình runtime.
- [ ] Xác minh thực địa V17.0.7 trên Xperia: đăng nhập lại sau Update Center phải giữ giao diện TMS OS đầy đủ và trở về trang cập nhật an toàn.
- [x] Khắc phục GitHub Actions Verify UCI payload: kiểm tra installer theo đường dẫn phát hành `scripts/install.sh`, không yêu cầu sai `install.sh` ở gốc ZIP và không tạo lại release.
- [x] Xác minh workflow GitHub Actions thủ công với release V17.0.7 sau khi sửa contract UCI, bảo đảm không báo thiếu installer ở gốc ZIP.
- [x] Khắc phục Update Center khi truy cập qua Cloudflare: polling phải chịu được 502 tạm thời trong lúc restart, xác minh bằng trạng thái worker bền vững và không hiển thị cảnh báo “Chưa xác nhận cập nhật” sai.
- [x] Đóng gói, phát hành và xác minh V17.0.8: restart PHP hẹp để không dừng Nginx/Cloudflare trong update; polling kiểm tra source trước khi kết luận thất bại.
- [ ] Xác minh thực địa V17.0.8 trên Xperia từ panel local: cập nhật phải hoàn tất không phát sinh 502 và không còn cảnh báo “Chưa xác nhận cập nhật” sai.
- [x] Tinh gọn Update Center: ẩn hoàn toàn Cập nhật nhanh khi mở trang và khi đã mới nhất; chỉ hiện thông tin bản mới cùng nút cập nhật sau lần Kiểm tra cập nhật xác nhận có release mới.
- [x] Đóng gói, phát hành và xác minh V17.0.9: giao diện Update Center chỉ hiện Cập nhật nhanh sau khi kiểm tra xác nhận có release mới.
- [ ] Xác minh thực địa V17.0.9 trên Xperia: trước khi kiểm tra không có khối Cập nhật nhanh; sau khi phát hiện bản mới mới hiện nút cập nhật bên dưới thông tin release.
- [x] Khắc phục báo cáo Telegram theo giờ ghi mốc UTC thay vì giờ Việt Nam: chuẩn hóa mốc báo cáo theo `Asia/Ho_Chi_Minh` và giữ nguyên lịch, dữ liệu truy cập cùng cấu hình bot.
- [x] Đóng gói và xác minh payload V17.0.10: checksum SHA-256, cấu trúc ZIP, đường nâng V17.0.9 → V17.0.10, storage và cấu hình Cloudflare đều được bảo toàn.
- [x] Phát hành V17.0.10 trên GitHub: đính kèm đúng `TMS_OS_LATEST.zip` và `RELEASE.json`, không tạo tag hoặc release trùng.
- [ ] Xác minh thực địa V17.0.10 trên Xperia: báo cáo Telegram tiếp theo phải hiển thị `Mốc gửi` đúng giờ Việt Nam (+07:00) so với thời điểm Telegram nhận tin.
- [x] Khắc phục Update Center V17.0.9 không có nút cập nhật khả dụng dù đã phát hiện V17.0.10: hợp nhất thông tin release và nút `Cập nhật ngay` vào khung `Phiên bản hiện tại` sau khi kiểm tra thành công.
- [x] Đóng gói và kiểm thử hotfix Update Center V17.0.11: xác minh đường nâng V17.0.9 → V17.0.11 một chạm, checksum, ZIP sạch và bảo toàn dữ liệu runtime/cấu hình dịch vụ.
- [x] Phát hành V17.0.11 trên GitHub: đính kèm đúng `TMS_OS_LATEST.zip` và `RELEASE.json`, không tạo tag hoặc release trùng.
- [ ] Xác minh thực địa V17.0.11 trên Xperia: nút `Cập nhật ngay` phải xuất hiện ngay dưới dòng phát hiện bản mới trong khung `Phiên bản hiện tại`, rồi cập nhật thành công.
- [x] Khắc phục artifact V17.0.11 bị CI từ chối: loại script xác minh nội bộ khỏi ZIP thiết bị, tái tạo checksum/metadata và xác minh lại release hiện có mà không tạo bản phát hành trùng.
- [x] Khắc phục markup form Update Center V17.0.11: đóng đúng thẻ hidden CSRF để nút `Cập nhật ngay` là phần tử HTML có thể thao tác, sau đó phát hành V17.0.12 cho đường nâng thủ công từ V17.0.9.
- [x] Đóng gói và kiểm thử payload V17.0.12: mô phỏng đường nâng V17.0.9 → V17.0.12, xác minh HTML nút cập nhật, checksum, ZIP sạch, UCI matrix và bảo toàn storage/Cloudflare.
- [x] Phát hành V17.0.12 trên GitHub: đính kèm đúng `TMS_OS_LATEST.zip` và `RELEASE.json`, không tạo tag hoặc release trùng.
- [ ] Xác minh thực địa V17.0.12 trên Xperia: nút `Cập nhật ngay` trong khung `Phiên bản hiện tại` phải bấm được, đưa panel lên V17.0.12 và giữ nguyên dữ liệu/cấu hình.
- [x] Khắc phục V17.0.9 từ chối `TMS_OS_LATEST.zip` ở bước `Kiểm tra và lưu`: xác định lỗi tương thích upload/staging và cung cấp đường nâng không yêu cầu cài lại Termux hoặc thay đổi dữ liệu. (Chi tiết: giới hạn upload PHP engine, xem mục V17.0.17)
- [x] Khắc phục hồi quy đường nâng V17.0.9: người dùng phải hạ về V17.0.7 mới có thể cập nhật lên V17.0.12; đã kiểm thử và bảo đảm V17.0.9 tự nâng trực tiếp ở các bản sau, không cần hạ phiên bản trung gian. (Chi tiết: UpdateService chịu lỗi kết nối + giới hạn upload, xem mục V17.0.17)
- [x] Củng cố Update Center sau hồi quy V17.0.9: nút `Cập nhật ngay` dùng submit chuẩn có dự phòng không phụ thuộc riêng vào JavaScript; lỗi tải ZIP thủ công hiển thị nguyên nhân theo mã upload an toàn, không lộ dữ liệu hệ thống.
- [x] Đóng gói và kiểm thử V17.0.13: mô phỏng V17.0.12 → V17.0.13, kiểm tra submit dự phòng, checksum, ZIP, verifier UCI, báo cáo Telegram, worker cập nhật, phiên đăng nhập và bảo toàn storage/Cloudflare.
- [x] Phát hành V17.0.13 trên GitHub: đính kèm đúng `TMS_OS_LATEST.zip` và `RELEASE.json`, không tạo tag hoặc release trùng.
- [ ] Xác minh thực địa V17.0.13 trên Xperia: từ V17.0.12, bấm Kiểm tra cập nhật rồi bấm Cập nhật ngay trong khung Phiên bản hiện tại; xác nhận cập nhật trực tiếp hoàn tất và thông báo lỗi upload (nếu có) rõ ràng.
- [x] Nâng cấp lệnh Telegram `/checkupdate`: kiểm tra bản mới, xác thực quản trị hai bước bằng mật khẩu trước khi áp dụng cập nhật, từ chối tuyệt đối khi sai mật khẩu và không ghi/lộ thông tin nhạy cảm.
- [x] Thêm phản hồi inline cho `/checkupdate`: khi có release mới, hiện đúng hai nút Telegram `Cập nhật` và `Bỏ qua`; nút `Cập nhật` yêu cầu mật khẩu nâng cấp do quản trị viên tự đặt trong Update Center.
- [x] Thêm mục mật khẩu nâng cấp trong Update Center: lưu hash một chiều, xác nhận khi thay đổi, không hiển thị lại giá trị, giới hạn thử sai và hết hạn phiên xác nhận Telegram trước khi áp dụng update.
- [x] Tách riêng tệp mật khẩu nâng cấp và phiên Telegram, ghi nguyên tử quyền 0600; xác thực nonce, hết hạn 5 phút, tối đa 3 lần sai và chống phát lại trước khi gọi hàng đợi GitHub sẵn có.
- [x] Mở rộng webhook Telegram nhận callback_query, xác thực secret/chat/user nhất quán, trả xác nhận callback và duy trì tương thích `/status`, `/help`.
- [x] Bổ sung hồi quy cho `/checkupdate`, nút Cập nhật/Bỏ qua, phiên mật khẩu đúng/sai/hết hạn/phát lại, CSRF Update Center và kiểm tra payload phát hành trước khi đóng gói.
- [ ] Chẩn đoán và khắc phục cài mới bị dừng khi mirror Termux trả HTTP 403: installer phải chọn mirror còn truy cập được hoặc dừng với hướng dẫn an toàn, không báo thiếu PHP gây hiểu nhầm.
- [x] Chẩn đoán và khắc phục Nginx 502 sau cài mới: kiểm tra PHP engine, cổng FastCGI, thư mục runtime và script khởi động trên Termux Android 7; không cài lại hoặc xóa dữ liệu để phục hồi panel.
- [x] Sửa Service Manager để Start/Restart Nginx xác minh PHP runtime trên loopback trước khi báo thành công, bổ sung hồi quy 502 và phát hành hotfix cập nhật trực tiếp.
- [x] Chẩn đoán lỗi Update Center V17.0.14 không kết nối được GitHub trên thiết bị mới để nhận V17.0.15: xác định 3 lỗi mã nguồn — httpGet nuốt toàn bộ lỗi cURL (DNS/TLS/HTTP 403), endpoint `www.github.com/api/v3` không tồn tại, không chịu lỗi IPv6 hỏng phổ biến trên Android.
- [x] Khắc phục V17.0.9 từ chối `TMS_OS_LATEST.zip` ở bước `Kiểm tra và lưu`: PHP engine chạy `-n` không nâng `upload_max_filesize`/`post_max_size` (mặc định 2M/8M) nhỏ hơn gói cập nhật; đã nâng 100M/110M ở php-cgi, php-http và php-fpm.
- [x] Khắc phục hồi quy đường nâng V17.0.9: gỡ blocker upload và thêm đường nâng chịu lỗi (`httpGetDetailed` chi tiết lỗi từng endpoint, ép IPv4 dự phòng, giữ fallback metadata RELEASE.json) để V17.0.9+ tự nâng trực tiếp, không cần hạ bản trung gian.
- [x] Đóng gói, kiểm thử và phát hành V17.0.17: payload 156 files chỉ chứa 5 source root; mô phỏng nâng cấp V17.0.16 → V17.0.17 bảo toàn storage/Cloudflare, refresh Service Worker; bổ sung hồi quy `tests/update_connectivity_resilience_test.php` và `tests/v17_0_17_update_payload_test.php`.
- [x] Xác minh thực địa V17.0.17 trên Xperia: cập nhật một chạm thành công, panel xác nhận V17.0.17, giữ phiên đăng nhập và dữ liệu.
- [x] Xác định lỗi mã nguồn khiến Update Center không kết nối được GitHub: tầng HTTP nuốt toàn bộ lỗi cURL (DNS/TLS/HTTP 403), endpoint `www.github.com/api/v3` không tồn tại và không chịu lỗi IPv6 hỏng phổ biến trên Android. Viết lại `httpGetDetailed` chi tiết lỗi từng endpoint, ép IPv4 dự phòng, bỏ endpoint sai, thêm API `/api/updates/diagnose` và nút Chẩn đoán kết nối trong Update Center.
- [x] Khắc phục V17.0.9 từ chối `TMS_OS_LATEST.zip` ở bước "Kiểm tra và lưu": PHP engine chạy `php-cgi -n`/`php -n`/`php-fpm` không nâng `upload_max_filesize`/`post_max_size` (mặc định 2M/8M) nhỏ hơn gói cập nhật; đã nâng lên 100M/110M ở cả ba chế độ và bổ sung hồi quy `tests/update_connectivity_resilience_test.php`.
- [x] Cứng hoá bảo mật panel V17.0.18: default-deny auth middleware ở Router (public trắng: /, /login, /telegram/webhook, /status, /api/public-status, /api/updates/run token riêng), rate limit đăng nhập 5 lần sai/15 phút khoá 15 phút, CommandRunner làm điểm gọi lệnh chuẩn, tổ chức lại routes/web.php giữ nguyên 134 route.
- [x] Đóng gói, kiểm thử và phát hành V17.0.18: mô phỏng nâng cấp V17.0.17 → V17.0.18, hồi quy security_hardening_test.php.
- [ ] Xác minh thực địa V17.0.18 trên Xperia: đăng nhập đúng vẫn vào được panel; sai 5 lần hiện thông báo khoá; API public status vẫn hoạt động khi chưa đăng nhập.
- [x] Backup tự động V17.0.19: AutoBackupService tạo backup qua BackupService (hiện trong Backup Center, khôi phục một chạm), lịch hằng ngày qua cron engine, retention 1-90 bản chỉ dọn bản [auto], offsite rclone, thông báo Telegram tuỳ chọn, CLI scripts/tms-auto-backup.php.
- [x] Đóng gói, kiểm thử và phát hành V17.0.19: mô phỏng nâng cấp V17.0.18 → V17.0.19, hồi quy auto_backup_service_test.php.
- [ ] Xác minh thực địa V17.0.19 trên Xperia: bật backup tự động, chạy thử ngay, kiểm tra cron job tms-auto-backup xuất hiện và (nếu có rclone) đẩy offsite thành công.
- [x] Cảnh báo vận hành V17.0.20: OperationalAlertsService kiểm tra mỗi 15 phút (bộ nhớ, RAM, pin 100% quá lâu, nhiệt độ, Tunnel rớt) với cooldown từng loại, gửi qua Telegram, cấu hình trong trang Thông báo; Guardian thêm auto-heal cloudflared (dò /proc) và crond với cấu hình CHECK_TUNNEL/CHECK_CRON.
- [x] Đóng gói, kiểm thử và phát hành V17.0.20: mô phỏng nâng cấp V17.0.19 → V17.0.20, hồi quy operational_alerts_service_test.php.
- [ ] Xác minh thực địa V17.0.20 trên Xperia: cài termux-api, bật cảnh báo, thử ngưỡng thấp để nhận Telegram; tunnel rớt phải được Guardian tự khởi động lại.
- [x] Hạ tầng kỹ thuật: CI chạy toàn bộ test PHP trên ubuntu (workflow php-tests, 2 tầng — lỗi môi trường Termux/Windows chỉ cảnh báo), license MIT, CHANGELOG tự sinh từ RELEASE.json (scripts/generate-changelog.php) và đã điền mục V17.0.17–V17.0.20.

- [x] Fix lỗi không thể upload tệp vào thư mục website mới tạo trong TMS OS, gồm kiểm tra quyền thư mục, kích thước request, timeout và phản hồi lỗi.

- [x] Bổ sung kiểm thử hồi quy cho upload vào thư mục website mới tạo và các trường hợp upload thất bại.

- [x] Cập nhật README GitHub với hướng dẫn preflight Termux, cài đủ pkg bắt buộc và kiểm tra trước khi chạy installer TMS OS.
