import React from "react";
import { Card, Row, Col, Statistic, Typography, Divider, Table, Tag } from "antd";
import {
    HomeOutlined,
    TeamOutlined,
    DollarOutlined,
    CalendarOutlined,
    StarOutlined,
} from "@ant-design/icons";

const { Title, Text } = Typography;

const Dashboard: React.FC = () => {
    const stats = [
        {
            title: "Tổng số Homestay",
            value: 128,
            icon: <HomeOutlined style={{ color: "#3b82f6" }} />,
        },
        {
            title: "Đơn đặt phòng",
            value: 342,
            icon: <CalendarOutlined style={{ color: "#f97316" }} />,
        },
        {
            title: "Khách hàng",
            value: 215,
            icon: <TeamOutlined style={{ color: "#10b981" }} />,
        },
        {
            title: "Doanh thu tháng",
            value: "58.200.000 ₫",
            icon: <DollarOutlined style={{ color: "#8b5cf6" }} />,
        },
    ];

    const recentBookings = [
        {
            key: "1",
            customer: "Nguyễn Văn A",
            homestay: "Homestay Đà Lạt",
            date: "25/10/2025",
            status: "Đã xác nhận",
        },
        {
            key: "2",
            customer: "Trần Thị B",
            homestay: "Homestay Nha Trang",
            date: "24/10/2025",
            status: "Chờ duyệt",
        },
        {
            key: "3",
            customer: "Lê Văn C",
            homestay: "Homestay Sapa",
            date: "22/10/2025",
            status: "Đã hủy",
        },
    ];

    const columns = [
        {
            title: "Khách hàng",
            dataIndex: "customer",
            key: "customer",
        },
        {
            title: "Homestay",
            dataIndex: "homestay",
            key: "homestay",
        },
        {
            title: "Ngày đặt",
            dataIndex: "date",
            key: "date",
        },
        {
            title: "Trạng thái",
            dataIndex: "status",
            key: "status",
            render: (status: string) => {
                let color = "blue";
                if (status === "Đã hủy") color = "red";
                else if (status === "Chờ duyệt") color = "orange";
                else if (status === "Đã xác nhận") color = "green";
                return <Tag color={color}>{status}</Tag>;
            },
        },
    ];

    return (
        <div>
            <Title level={3} style={{ marginBottom: 24, color: "#1e3a8a" }}>
                📊 Tổng quan hệ thống
            </Title>

            {/* Thống kê nhanh */}
            <Row gutter={[24, 24]}>
                {stats.map((item) => (
                    <Col xs={24} sm={12} md={12} lg={6} key={item.title}>
                        <Card
                            bordered={false}
                            style={{
                                borderRadius: 16,
                                boxShadow: "0 2px 8px rgba(0,0,0,0.06)",
                            }}
                        >
                            <Statistic
                                title={
                                    <span style={{ fontWeight: 600, color: "#64748b" }}>
                                        {item.title}
                                    </span>
                                }
                                value={item.value}
                                prefix={item.icon}
                                valueStyle={{ fontSize: 22, color: "#0f172a" }}
                            />
                        </Card>
                    </Col>
                ))}
            </Row>

            <Divider />

            {/* Bảng đặt phòng gần đây */}
            <Row gutter={[24, 24]} style={{ marginTop: 12 }}>
                <Col span={24}>
                    <Card
                        title={
                            <SpaceBetween>
                                <span style={{ fontWeight: 600, fontSize: 16 }}>
                                    🧾 Đơn đặt phòng gần đây
                                </span>
                            </SpaceBetween>
                        }
                        bordered={false}
                        style={{
                            borderRadius: 16,
                            boxShadow: "0 1px 6px rgba(0,0,0,0.05)",
                        }}
                    >
                        <Table
                            columns={columns}
                            dataSource={recentBookings}
                            pagination={false}
                            rowHoverable
                        />
                    </Card>
                </Col>
            </Row>

            {/* Đánh giá tổng quan */}
            <Row gutter={[24, 24]} style={{ marginTop: 24 }}>
                <Col span={24}>
                    <Card
                        bordered={false}
                        style={{
                            borderRadius: 16,
                            background: "linear-gradient(90deg, #f0f7ff 0%, #e0f2fe 100%)",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "space-between",
                            padding: 24,
                        }}
                    >
                        <div>
                            <Title level={4} style={{ color: "#1e3a8a", marginBottom: 4 }}>
                                Tổng điểm đánh giá trung bình
                            </Title>
                            <Text type="secondary">Dựa trên 250 lượt đánh giá</Text>
                        </div>
                        <div style={{ textAlign: "center" }}>
                            <StarOutlined style={{ fontSize: 40, color: "#facc15" }} />
                            <Title level={2} style={{ color: "#f59e0b", margin: 0 }}>
                                4.8 / 5.0
                            </Title>
                        </div>
                    </Card>
                </Col>
            </Row>
        </div>
    );
};

const SpaceBetween: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <div
        style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
        }}
    >
        {children}
    </div>
);

export default Dashboard;
