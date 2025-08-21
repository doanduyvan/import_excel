@extends('layout')
@section('title', 'Bảng điều khiển')

<style>
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
</style>

@section('content')
  <h1 class="">Trang setting!</h1>

  <div class="check-email">
    <p>Check Email</p>
    <div class="check-email-loading"></div>
    <button>Check Email</button>
  </div>
  <div class="check-email-result">

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
  </script>

@endsection
