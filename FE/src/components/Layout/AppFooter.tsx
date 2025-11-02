import { Layout, Row, Col, Typography, Divider } from "antd";
import { Content } from "antd/es/layout/layout";
import {
    FacebookFilled,
    InstagramFilled,
    YoutubeFilled,
    TwitterOutlined,
    HomeOutlined,
    PhoneOutlined,
    MailOutlined,
} from "@ant-design/icons";
import React from "react";

const { Title, Paragraph } = Typography;

const AppFooter: React.FC = () => {
    return (
        <>
            {/* FOOTER */}
            <Content
                style={{ padding: "60px 50px", background: "#001529", color: "#fff" }}
            >
                <Row
                    gutter={[32, 32]}
                    justify="space-between"
                    style={{ maxWidth: 1200, margin: "0 auto" }}
                >
                    <Col xs={24} sm={12} md={6}>
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: 8,
                                marginBottom: 16,
                            }}
                        >
                            <HomeOutlined style={{ fontSize: 28, color: "#1677ff" }} />
                            <Title level={3} style={{ margin: 0, color: "#fff" }}>
                                HomestayBooking
                            </Title>
                        </div>
                        <Paragraph style={{ color: "#d9d9d9" }}>
                            Đặt homestay dễ dàng – Trải nghiệm tuyệt vời – Giá hợp lý
                        </Paragraph>
                    </Col>

                    <Col xs={24} sm={12} md={4}>
                        <Title level={4} style={{ color: "#fff" }}>
                            Liên hệ
                        </Title>
                        <Paragraph style={{ color: "#d9d9d9" }}>
                            <PhoneOutlined /> 0939 999 999
                        </Paragraph>
                        <Paragraph style={{ color: "#d9d9d9" }}>
                            <MailOutlined /> support@homestay.vn
                        </Paragraph>
                    </Col>

                    <Col xs={24} sm={12} md={4}>
                        <Title level={4} style={{ color: "#fff" }}>
                            Mạng xã hội
                        </Title>
                        <div style={{ display: "flex", gap: 16 }}>
                            <FacebookFilled style={{ fontSize: 24, color: "#1677ff" }} />
                            <InstagramFilled style={{ fontSize: 24, color: "#e91e63" }} />
                            <YoutubeFilled style={{ fontSize: 24, color: "#ff4d4f" }} />
                            <TwitterOutlined style={{ fontSize: 24, color: "#40a9ff" }} />
                        </div>
                    </Col>
                </Row>

                <Divider style={{ borderColor: "#333", margin: "32px 0" }} />

                <Paragraph style={{ textAlign: "center", color: "#999" }}>
                    © {new Date().getFullYear()} HomestayBooking – All rights reserved.
                </Paragraph>
            </Content>
        </>
    );
};

export default AppFooter;
