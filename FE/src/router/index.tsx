import { createBrowserRouter } from "react-router-dom";
import { clientRoutes } from "./clientRoutes"; // Import client routes
import { adminRoutes } from "./adminRoutes";   // Import admin routes

// Kết hợp cả hai mảng routes lại
const router = createBrowserRouter([
    ...clientRoutes,
    ...adminRoutes,

    // Bạn cũng có thể thêm một route 404 ở đây
    // { path: "*", element: <NotFoundPage /> } 
]);

export default router;
