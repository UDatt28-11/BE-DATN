import { Layout, Menu, Typography, Button, Space } from "antd";
import { UserOutlined, HomeOutlined } from "@ant-design/icons";
import { Link } from "react-router-dom";
import React from "react";
// Lưu ý: Đường dẫn import này phải khớp với cấu trúc thư mục của bạn
import UserMenu from "../User Menu/usermenucomponet";

const { Header } = Layout;
const { Title } = Typography;

// Định nghĩa props cho AppHeader
interface AppHeaderProps {
    isLoggedIn: boolean;
}

// ĐÃ SỬA: Truyền AppHeaderProps vào React.FC và destructuring { isLoggedIn }
const AppHeader: React.FC<AppHeaderProps> = ({ isLoggedIn }) => {
    return (
        <Header
            style={{
                position: "fixed",
                top: 0,
                width: "100%",
                zIndex: 1000,
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                background: "#fff",
                padding: "0 50px",
                boxShadow: "0 2px 8px rgba(0,0,0,0.06)",
                height: 70,
            }}
        >
            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <HomeOutlined style={{ fontSize: 28, color: "#1677ff" }} />
                <Title level={3} style={{ margin: 0, color: "#1677ff", fontWeight: 700 }}>
                    HomestayBooking
                </Title>
            </div>

            <Menu
                mode="horizontal"
                defaultSelectedKeys={[""]}
                items={[
                    { key: "1", label: <Link to="/">Trang chủ</Link> },
                    { key: "2", label: <Link to="/homestay">Khám phá  </Link> },
                    { key: "3", label: <Link to="/about">Giới thiệu</Link> },
                    { key: "4", label: <Link to="/promotion">Ưu Đãi   </Link> },
                    { key: "5", label: <Link to="/contact">Liên Hệ </Link> },
                ]}
                style={{
                    borderBottom: "none",
                    flex: 1,
                    justifyContent: "center",
                    fontSize: 15,
                    fontWeight: 500,
                }}
            />

            <Space size="middle">
                {/* LOGIC HIỂN THỊ CÓ ĐIỀU KIỆN */}
                {isLoggedIn ? (
                    <UserMenu />
                ) : (
                    <>
                        <Link to="/login">
                            <Button type="text" icon={<UserOutlined />} size="small">
                                Đăng nhập
                            </Button>
                        </Link>
                        <Link to="/register">
                            <Button type="primary" size="large" style={{ fontWeight: 150 }}>
                                Đăng ký
                            </Button>
                        </Link>
                    </>
                )}
            </Space>
        </Header>
    );
};

export default AppHeader;
