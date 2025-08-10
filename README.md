<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

-   **[Vehikl](https://vehikl.com/)**
-   **[Tighten Co.](https://tighten.co)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Cubet Techno Labs](https://cubettech.com)**
-   **[Cyber-Duck](https://cyber-duck.co.uk)**
-   **[Many](https://www.many.co.uk)**
-   **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
-   **[DevSquad](https://devsquad.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
-   **[OP.GG](https://op.gg)**
-   **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
-   **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# quy trình sử lí

Tạo file ImportCommand, file này sẽ gọi service, viết toàn bộ sử lí trong service
trong file command sẽ gọi đến service layer và gọi db để insert

nếu là file tender thì truncase bảng tender để xóa nhanh dữ liệu và reset index về 1
trong file này sẽ có dữ liệu customer-tender. customer có thể bị trùng trong db cần kiểm tra trước.

dữ liệu đầu vào 20k dòng sẽ được lưu trong 1 mảng.

sau đó dùng array_chunk để tách ra các mảng con, mỗi mảng có giá trị 1k phần tử, tổng cộng có khoảng 20 phần tử.
sau đó sử lí lần lượt từng mảng con.

dùng where IN() tại bảng customer để lấy ra những customer đã có, số còn lại thì sẽ insert hàng loạt
sau khi insert vào customer. tiếp tục lấy where in [customer code] để lấy ra id và gán vào tender để insert hàng loạt

dữ liệu được thêm mới có dạng
[
[
'tên cột' => dữ liệu,
'tên cột' => dữ liệu,
],
[
'tên cột' => dữ liệu,
'tên cột' => dữ liệu,
]
]


phương thức handleAll(){
   gọi sử lí ở đây. cái này tôi tự làm được
}
dùng để gọi tất các các phương thức khác để sử lí

receiveMail(){
    mở kết nối mail
    lọc các email chưa đọc tronng 10 ngày gần nhất
    lọc các email có tiêu đều chứa nội dung trong mảng, mảng này có thể tùy chỉnh ['sales' => 'dailynetSales', 'tender' => 'tenderstatus]
    lọc tiếp trong các mail đó để lấy ra file .zip có thể 1 file hoặc nhiều file .zip trong 1 mail
    sau đó trả về 1 mảng chứa các email_number và mảng file .zip
    không đánh dấu là đã đọc
    đóng kết nối mail
}

handleFileZip(nhận mảng file .zip từ receiveMail){
    giải nén file .zip và lấy ra file excel trong đó, trong đó có thể chứa 1 hay nhiều file excel
    lưu file excel vào bộ nhớ tạm để sử lí, không cần lưu vào host.
    trả về 1 mảng chứa đường dẫn file excel tại bộ nhớ tạm
}

handleExcel(nhận đường dẫn trong file excel){
    đọc file excel và trả về mảng dữ liệu đúng với cấu trúc db
}

handleInsertDB(nhận mảng dữ liệu từ handleExcel){
    thực hiện ghi vào db
}

handleStatusMail(mảng chứa các email_number đã sủ lí thành công){
    mở kết nối email
    chuyển trạng thái
    đóng kết nối
}

tại mỗi phương thức trả về sẽ có cấu trúc

         return [
                    'status' => '200', // trạng thái thành công
                    'is_next' => false,  // có tiếp tục chạy tiếp hay không
                    'is_err' => false, // có phải lỗi hay không
                    'message' => "Không có email trong $days ngày gần đây.",
                    'data' => null
                ];


      1 => 
  array (
    'customer_code' => '30191128',
    'customer_name' => 'Nha Thuoc Hong Dai',
    'area' => 'Thanh Pho Ho Chi Minh',
    'sap_item_code' => '21216728',
    'item_short_description' => 'LACIPIL TAB 4MG 28\'S',
    'order_number' => '272838611',
    'invoice_number' => '1229451860',
    'contract_number' => '0',
    'expiry_date' => '07/06/2026',
    'selling_price' => 91655.63,
    'commercial_quantity' => 170,
    'invoice_confirmed_date' => '15/01/2025',
    'net_sales_value' => 15581457,
    'accounts_receivable_date' => '15/01/2025',
  ),
  2 => 
  array (
    'customer_code' => '30191083',
    'customer_name' => 'Ho Kinh Doanh Hai Minh',
    'area' => 'Thanh Pho Ho Chi Minh',
    'sap_item_code' => '21216728',
    'item_short_description' => 'LACIPIL TAB 4MG 28\'S',
    'order_number' => '272801616',
    'invoice_number' => '1229448514',
    'contract_number' => '0',
    'expiry_date' => '01/03/2026',
    'selling_price' => 92565.81,
    'commercial_quantity' => 80,
    'invoice_confirmed_date' => '15/01/2025',
    'net_sales_value' => 7405265,
    'accounts_receivable_date' => '15/01/2025',
  ),

đây là dữ liệu đọc từ file excel, đã mapping đúng key chính là cột trong db còn giá trị chính là dữ liệu.
hiện tại trong file này dữ liệu trùng rất nhiều.
trong db của tôi có 4 bảng, dùng để lưu các giá trị như trên
đầu tiên là bảng customer sẽ lưu 
    'id' => khi insert tự động tăng
    'customer_code' => '30191128', cột này sẽ là UNIQUE, trùng thì bỏ qua, không cập nhật
    'customer_name' => 'Nha Thuoc Hong Dai',
    'area' => 'Thanh Pho Ho Chi Minh',
    'create_at' => khi insert tự động nhận lúc insert
    'update_at' => khi insert tự động nhận lúc insert
tiếp đến là bảng sản phẩm sẽ lưu
    'id' => tự động tăng
    'sap_item_code' => '21216728', cột này cũng là UNIQUE, trùng thì bỏ qua không cập nhật
    'item_short_description' => 'LACIPIL TAB 4MG 28\'S',
còn lại là bảng salse sẽ lưu
    'id' => tự động tăng
    'order_number' => '272838611', cột này UNIQUE, trùng thì bỏ qua không cập nhật
    'invoice_number' => '1229451860',
    'contract_number' => '0',
    'expiry_date' => '07/06/2026',
    'selling_price' => 91655.63,
    'commercial_quantity' => 170,
    'invoice_confirmed_date' => '15/01/2025',
    'net_sales_value' => 15581457,
    'accounts_receivable_date' => '15/01/2025',
    'customer_id' => khóa chính của bảng customer, so sánh dựa trên customer_code trong từng row của file excel
có 1 bản trung gian để lưu mối quan hệ nhiều nhiều giữa bảng salse và bảng product
đó là bảng products_sales, trong mỗi row của file excel có cột sap_item_code là mã sản phẩm, và cột order_number mã salse để biết nên ghi mối quan hệ
'product_id' => id của bảng product,
'sales_id' => id của bảng sales