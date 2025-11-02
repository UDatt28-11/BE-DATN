import { RouteObject } from "react-router-dom";
import AppLayout from "../components/Layout/AppLayout";

// 🏠 Trang người dùng
import HomePage from "../pages/Clients/Home/HomePage";
import AboutPage from "../pages/Clients/About/AboutPage";
import LoginPage from "../pages/Clients/Auth/LoginPage";
import RegisterPage from "../pages/Clients/Auth/RegisterPage";

// --- Sửa đường dẫn cho nhất quán ---
import HomestayListPage from "../pages/Clients/Homestay/HomestayListPage";
// --- Import trang chi tiết (từ Canvas) ---
import HomestayDetailPage from "../pages/Clients/Homestay/HomestayDetailPage";
import PromotionsPage from "../pages/Clients/Promotions/PromotionsPage";
import ContactPage from "../pages/Clients/Contact/ContactPage";


// 🧭 Router Client
export const clientRoutes: RouteObject[] = [
    {
        path: "/",
        element: <AppLayout />,
        children: [
            { index: true, element: <HomePage /> },
            { path: "about", element: <AboutPage /> },
            { path: "homestay", element: <HomestayListPage /> },
            { path: "promotion", element: <PromotionsPage /> },
            { path: "contact", element: <ContactPage /> },

            // --- THÊM MỚI: Route cho trang chi tiết ---
            // Nó sử dụng :id để lấy tham số từ URL
            { path: "homestay/:id", element: <HomestayDetailPage /> }
        ],
    },
    {
        path: "/login",
        element: <LoginPage />,
    },
    {
        path: "/register",
        element: <RegisterPage />,
    },
];
