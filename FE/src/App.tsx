import React from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";

// Import Context Provider
// Đảm bảo đường dẫn này đúng: src/context/AuthContext.tsx
import { AuthProvider } from "./context/AuthContext";

// Import các trang (Pages)
// SỬA LỖI ĐƯỜNG DẪN: Bỏ "../src/" và dùng "./" (hoặc chỉ "./pages/...")
import HomePage from "./pages/Clients/Home/HomePage";
import LoginPage from "./pages/Clients/Auth/LoginPage";
import RegisterPage from "./pages/Clients/Auth/RegisterPage";

// Import CSS của Ant Design (Nếu chưa import ở index.tsx hoặc main.tsx)
// import 'antd/dist/antd.css'; 

const App: React.FC = () => {
  return (
    <BrowserRouter>
      {/* AuthProvider phải bọc ngoài Routes 
        để cung cấp context cho toàn bộ ứng dụng 
      */}
      <AuthProvider>
        <Routes>
          {/* Trang chủ */}
          <Route path="/" element={<HomePage />} />

          {/* Các trang xác thực */}
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />

          {/* Bạn có thể thêm các route khác của mình ở đây */}
          {/* Ví dụ: <Route path="/about" element={<AboutPage />} /> */}
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
};

export default App;

