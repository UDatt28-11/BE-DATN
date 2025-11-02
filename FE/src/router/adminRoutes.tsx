import { RouteObject } from "react-router-dom";
import AdminLayout from "../layouts/AdminLayout";

// Dashboard
import Dashboard from "../pages/Admin/Dashboard";

// Quản lý phòng
import ListRoom from "../pages/Admin/quanliphong/ListRoom";
import AddRoom from "../pages/Admin/quanliphong/addroom";
import EditRoom from "../pages/Admin/quanliphong/editroom";

// Quản lý danh mục
import ListCategory from "../pages/Admin/quanlidanhmuc/listcategory";
import AddCategory from "../pages/Admin/quanlidanhmuc/addcategory";
import EditCategory from "../pages/Admin/quanlidanhmuc/editcategory";

// Quản lý tiện ích (Amenities)
import ListAmenity from "../pages/Admin/quanlitienich/listamenity";
import AddAmenity from "../pages/Admin/quanlitienich/addamenity";
import EditAmenity from "../pages/Admin/quanlitienich/editamenity";
import VariantAmenity from "../pages/Admin/quanlitienich/variantamenity";

// Quản lý lưu trú (Accommodations)
import ListAccommodation from "../pages/Admin/quanliluutru/listaccommodation";
import AddAccommodation from "../pages/Admin/quanliluutru/addaccommodation";
import EditAccommodation from "../pages/Admin/quanliluutru/editaccommodation";
import ViewAccommodation from "../pages/Admin/quanliluutru/viewaccommodation";

// Các module khác

import ListBooking from "../pages/Admin/quanlidatphong/listbooking";
import AddBooking from "../pages/Admin/quanlidatphong/addbooking";
import EditBooking from "../pages/Admin/quanlidatphong/editbooking";
import ViewBooking from "../pages/Admin/quanlidatphong/viewbooking";
import ListUser from "../pages/Admin/quanlinguoidung/ListUser";
import AddUser from "../pages/Admin/quanlinguoidung/AddUser";
import EditUser from "../pages/Admin/quanlinguoidung/EditUser";
import BlockedUsers from "../pages/Admin/quanlinguoidung/BlockedUsers";
import ListPromotion from "../pages/Admin/quanlimagiamgia/listPromotion";
import AddPromotion from "../pages/Admin/quanlimagiamgia/addPromotion";
import EditPromotion from "../pages/Admin/quanlimagiamgia/editPromotion";
import ListReview from "../pages/Admin/quanlidanhgia/listReview";
import ListMessage from "../pages/Admin/quanlibinhluan/listMessage";

// SỬA ĐỔI: Chuyển từ export một object thành một mảng (array)
// để nhất quán với clientRoutes
export const adminRoutes: RouteObject[] = [
  {
    path: "/admin",
    element: <AdminLayout />,
    children: [
      { path: "", element: <Dashboard /> },
      { path: "dashboard", element: <Dashboard /> },

      // Quản lý phòng
      { path: "listing", element: <ListRoom /> },
      { path: "listing/add", element: <AddRoom /> },
      { path: "listing/edit/:id", element: <EditRoom /> },

      // Quản lý dat phòng
      { path: "booking", element: <ListBooking /> },
      { path: "booking/add", element: <AddBooking /> },
      { path: "booking/edit/:id", element: <EditBooking /> },
      { path: "booking/view/:id", element: <ViewBooking /> },

      // Quản lý danh mục
      { path: "category", element: <ListCategory /> },
      { path: "category/add", element: <AddCategory /> },
      { path: "category/edit/:id", element: <EditCategory /> },

      // Quản lý tiện ích (Amenities)
      { path: "amenities", element: <ListAmenity /> },
      { path: "amenities/add", element: <AddAmenity /> },
      { path: "amenities/edit/:id", element: <EditAmenity /> },
      { path: "amenities/variant/:id", element: <VariantAmenity /> },

      // Quản lý lưu trú
      { path: "accommodations", element: <ListAccommodation /> },
      { path: "accommodations/add", element: <AddAccommodation /> },
      { path: "accommodations/edit/:id", element: <EditAccommodation /> },
      { path: "accommodations/view/:id", element: <ViewAccommodation /> },

      // Quản lý user
      { path: "user", element: <ListUser /> },
      { path: "user/add", element: <AddUser /> },
      { path: "user/edit/:id", element: <EditUser /> },
      { path: "user/blocked", element: <BlockedUsers /> },

      // Quản lý mã giảm giá
      { path: "promotion", element: <ListPromotion /> },
      { path: "promotion/add", element: <AddPromotion /> },
      { path: "promotion/edit/:id", element: <EditPromotion /> },

      // Quản lý mã đánh giá
      { path: "review", element: <ListReview /> },

      // Quản lý bình luận
      { path: "message", element: <ListMessage /> },
    ],
  },
];

