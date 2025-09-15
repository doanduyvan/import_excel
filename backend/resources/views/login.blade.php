<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Document</title>
  <style>
    body {
      background: #f0f2f5;
      font-family: Arial, sans-serif;
      height: 100vh;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-box {
      background: #fff;
      padding: 32px 28px;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.09);
      width: 320px;
    }

    .login-box h2 {
      margin-bottom: 20px;
      text-align: center;
      font-weight: 600;
    }

    .login-box input[type="text"],
    .login-box input[type="password"] {
      width: 100%;
      padding: 10px 14px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
      outline: none;
      box-sizing: border-box;
    }

    .login-box button {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 8px;
      background: #1976d2;
      color: #fff;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }

    .login-box button:hover {
      background: #1565c0;
    }

    .login-box .note {
      text-align: center;
      color: #888;
      margin-top: 18px;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <div class="container mt-5">
    <form class="login-box">
      <h2>Đăng nhập</h2>
      <input type="text" placeholder="Tên đăng nhập" required>
      <input type="password" placeholder="Mật khẩu" required>
      <button type="submit">Đăng nhập</button>
      <div class="note"><a href="#">Quên mật khẩu?s</a></div>
    </form>
  </div>

  <script>
    document.querySelector('.login-box').addEventListener('submit', function(e) {
      e.preventDefault();
      // Xử lý đăng nhập ở đây
      //   alert('Đăng nhập thành công!');\
      const username = this.querySelector('input[type="text"]').value;
      const password = this.querySelector('input[type="password"]').value;
      console.log(`Username: ${username}, Password: ${password}`);
      // Gửi dữ liệu đăng nhập đến server hoặc xử lý logic đăng nhập
      // Ví dụ: gửi AJAX request đến server để xác thực
      fetch('/ajax/test', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            username,
            password
          })
        })
        .then(response => response.json())
        .then(data => {
          // if (data.success) {
          //   alert('Đăng nhập thành công!');
          //   window.location.href = '/dashboard';
          // } else {
          //   alert('Đăng nhập thất bại. Vui lòng thử lại.');
          // }
          console.log(data);
        })
        .catch(error => {
          console.error('Lỗi:', error);
          //   alert('Đã xảy ra lỗi khi đăng nhập. Vui lòng thử lại sau.');
        });
    });
  </script>

</body>

</html>
