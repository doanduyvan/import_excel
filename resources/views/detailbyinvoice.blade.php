@extends('layout')
@section('title', 'Bảng điều khiển')


@section('content')
  <h1 class="">Detail by invoice</h1>

  <div class="detailbyinvoice">
    <div class="render"></div>
  </div>

  <script>
    // document.querySelector('.check-email button').addEventListener('click', function() {
    //   const loadingIndicator = document.querySelector('.check-email-loading');
    //   loadingIndicator.classList.add('active');
    //   const resultDiv = document.querySelector('.check-email-result');
    //   resultDiv.innerHTML = ``;
    // });
    const fieldLabels = [{
        key: "fullname",
        label: "Họ tên"
      },
      {
        key: "customer_code",
        label: "Mã khách hàng"
      },
      {
        key: "customer_name",
        label: "Tên khách hàng"
      },
      {
        key: "order_number",
        label: "Số đơn hàng"
      },
      {
        key: "commercial_quantity",
        label: "Số lượng"
      },
      {
        key: "invoice_confirmed_date",
        label: "Ngày xác nhận hóa đơn"
      },
      {
        key: "expiry_date",
        label: "Hạn dùng"
      },
      {
        key: "item_short_description",
        label: "Sản phẩm"
      }
    ];

    fetch('/ajax/detailbyinvoice')
      .then(response => response.json())
      .then(data => {
        console.log(data);
        // render(data);
        document.querySelector(".render").innerHTML = renderTable(data);

      })
      .catch(error => {
        console.error('Error:', error);
        alert('Đã xảy ra lỗi khi kiểm tra email.');
      })
      .finally(() => {
        // loadingIndicator.classList.remove('active');
      });

    function renderTable(dataArr, options = {}) {
      let html = `<table class="custom-table">
        <caption>Danh sách khách hàng</caption>
        <thead>
          <tr>
            <th>STT</th>
            ${fieldLabels.map(f => `<th>${f.label}</th>`).join('')}
          </tr>
        </thead>
        <tbody>`;
      dataArr.forEach((item, idx) => {
        html += `<tr>
          <td>${idx + 1}</td>
          ${fieldLabels.map(f => `<td>${item[f.key] ?? ""}</td>`).join('')}
        </tr>`;
      });
      html += `</tbody></table>`;
      return html;
    }
  </script>

@endsection
