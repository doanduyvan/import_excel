@extends('layout')
@section('title', 'Bảng điều khiển')

<style>
  .btn {
    padding: 10px 20px;
    background-color: #1976d2;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  .btn:hover {
    background-color: #1565c0;
  }

  .check-email {
    margin-top: 20px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
  }

  .check-email button {
    padding: 10px 20px;
    background-color: #1976d2;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  .check-email-loading {
    width: 30px;
    height: 30px;
    border: 2px solid #ccc;
    border-top: 2px solid #1976d2;
    border-right: 2px solid #1976d2;
    border-left: 2px solid #1976d2;

    border-radius: 50%;
    animation: spin 1s linear infinite;
    display: none;
  }

  .check-email-loading.active {
    display: block;
  }

  @keyframes spin {
    0% {
      transform: rotate(0deg);
    }

    100% {
      transform: rotate(360deg);
    }
  }

  .check-email-result {
    padding: 20px;
    color: #ff0000;
  }

  .title-account {
    padding: 15px;
  }

  .content-account>div {
    padding: 15px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
  }

  .loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    display: none;
  }

  .loading>div {
    width: 40px;
    height: 40px;
    border: 4px solid #ccc;
    border-top: 4px solid #1976d2;
    border-right: 4px solid #1976d2;
    border-left: 4px solid #1976d2;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  .loading.active {
    display: flex;
  }

  .err-account {
    color: red;
    padding: 0 15px;
  }
</style>

@section('content')
  <h1 class="">Trang setting!</h1>
  <div class="loading">
    <div></div>
  </div>
  <div class="check-email">
    <p>Check Email</p>
    <div class="check-email-loading"></div>
    <button>Check Email</button>
  </div>
  <div class="check-email-result">

  </div>
  <hr>

  <h3 class="title-account">Import file Account</h3>
  <div class="content-account">
    <div>
      <input accept=".xlsx" type="file" id="input-account" placeholder="Nhập link file excel">
      <button class="btn" id="btn-account">Gửi đi</button>
    </div>

    <p class="err-account"></p>
  </div>

  <script>
    document.querySelector('.check-email button').addEventListener('click', function() {
      const loadingIndicator = document.querySelector('.check-email-loading');
      loadingIndicator.classList.add('active');
      const resultDiv = document.querySelector('.check-email-result');
      resultDiv.innerHTML = ``;
      fetch('/ajax/checkmail')
        .then(response => response.json())
        .then(data => {
          console.log(data);

          for (const mes of data) {
            resultDiv.innerHTML += `<p>${mes}</p>`;
          }
          if (data.length === 0) {
            resultDiv.innerHTML = `<p>Thành công</p>`;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Đã xảy ra lỗi khi kiểm tra email.');
        })
        .finally(() => {
          loadingIndicator.classList.remove('active');
        });
    });


    document.getElementById('btn-account').addEventListener('click', function() {
      const input = document.getElementById('input-account');
      const errorEl = document.querySelector('.err-account');
      errorEl.textContent = ''; // Xóa thông báo cũ

      if (!input.files || !input.files.length) {
        errorEl.textContent = 'Vui lòng chọn file Excel (.xlsx)!';
        return;
      }

      const file = input.files[0];
      const fileName = file.name.toLowerCase();

      // Chỉ cho phép file .xlsx
      if (!fileName.endsWith('.xlsx')) {
        errorEl.textContent = 'Chỉ cho phép file Excel .xlsx';
        return;
      }
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const data = new FormData();
      data.append('file', file);
      const loadingIndicator = document.querySelector('.loading');
      loadingIndicator.classList.add('active');
      fetch('/ajax/importaccount', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken, // lấy từ meta hoặc từ biến server truyền xuống
          },
          body: data
        })
        .then(response => response.json())
        .then(data => {
          errorEl.textContent = data.message || '';
          console.log(data);
          input.value = ''; // reset input file
        })
        .catch(error => {
          console.error('Error:', error);
          errorEl.textContent = 'Đã xảy ra lỗi khi gửi file.';
        })
        .finally(() => {
          loadingIndicator.classList.remove('active');
        });

      // Nếu tới đây là hợp lệ, bạn có thể xử lý gửi file tiếp
      errorEl.textContent = 'File hợp lệ, đang gửi...';
    });
  </script>

@endsection
