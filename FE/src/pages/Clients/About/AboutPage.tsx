import { Layout, Typography, Row, Col, Card, Statistic, Avatar, Divider, Timeline, Tag, Button } from "antd";
import { SmileOutlined, TeamOutlined, HomeOutlined, TrophyOutlined, HeartOutlined, StarFilled, GlobalOutlined, RocketOutlined, CustomerServiceOutlined, DollarOutlined, ThunderboltOutlined, LockOutlined, CheckCircleOutlined } from "@ant-design/icons";
import { useState, useEffect } from "react";



const { Header, Content, Footer } = Layout;
const { Title, Paragraph, Text } = Typography;
import AppHeader from "../../../components/Layout/AppHeader";
import AppFooter from "../../../components/Layout/AppFooter";

export default function AboutPage() {
    const banners = [
        "https://images.unsplash.com/photo-1522708323590-d24dbb6b0267",
        "https://images.unsplash.com/photo-1505693416388-ac5ce068fe85",
        "https://images.unsplash.com/photo-1490645935967-10de6ba17061"
    ];

    const [index, setIndex] = useState(0);

    useEffect(() => {
        const interval = setInterval(() => {
            setIndex((prev) => (prev + 1) % banners.length);
        }, 1500);

        return () => clearInterval(interval);
    }, []);
    return (
        <Layout>
            {/* BANNER */}
            < AppHeader />
            <div
                style={{
                    marginTop: 70,
                    position: 'relative',
                    transition: 'background-image 1.5s ease-in-out',
                    backgroundImage: `linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('${banners[index]}')`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    height: '75vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    textAlign: 'center',
                }}
            >
                <div style={{ maxWidth: 900, padding: '0 20px' }}>
                    <Title
                        level={1}
                        style={{
                            color: '#fff',
                            fontSize: 58,
                            fontWeight: 800,
                            marginBottom: 24,
                            textShadow: '0 4px 20px rgba(0,0,0,0.5)',
                            lineHeight: 1.2,
                        }}
                    >
                        Về HomestayBooking
                    </Title>
                    <Paragraph
                        style={{
                            color: '#fff',
                            fontSize: 22,
                            marginBottom: 40,
                            textShadow: '0 2px 10px rgba(0,0,0,0.5)',
                            maxWidth: 700,
                            margin: '0 auto 40px',
                        }}
                    >
                        Nền tảng đặt homestay hàng đầu Việt Nam - Kết nối du khách với những trải nghiệm lưu trú độc đáo
                    </Paragraph>
                    <Button
                        type="primary"
                        size="large"
                        style={{
                            height: 54,
                            fontSize: 17,
                            fontWeight: 600,
                            paddingLeft: 48,
                            paddingRight: 48,
                            borderRadius: 8,
                        }}
                    >
                        Khám phá homestay ngay
                    </Button>
                </div>
            </div>

            {/* GIỚI THIỆU */}
            <Content style={{ padding: '100px 50px', background: '#fff' }}>
                <Row gutter={[80, 64]} align="middle" style={{ maxWidth: 1200, margin: '0 auto' }}>
                    <Col xs={24} md={12}>
                        <Tag color="blue" style={{ marginBottom: 16, fontSize: 13, padding: '4px 12px' }}>
                            Câu chuyện của chúng tôi
                        </Tag>
                        <Title level={2} style={{ fontSize: 40, fontWeight: 700, marginBottom: 24 }}>
                            Chúng tôi là ai?
                        </Title>
                        <Paragraph style={{ fontSize: 16, color: '#666', marginBottom: 32 }}>
                            Tại BookStay, chúng tôi tin rằng việc nghỉ dưỡng không chỉ là ngủ qua đêm – mà là tận hưởng một trải nghiệm sống hoàn toàn khác.
                            Vì vậy, chúng tôi chọn lọc những homestay có concept rõ ràng, có gu, có chiều sâu câu chuyện, và thật sự mang lại cảm xúc.
                            Dữ liệu được cập nhật liên tục theo đánh giá, lượt đặt, mức độ yêu thích và mức độ tin tưởng từ cộng đồng du lịch.
                        </Paragraph>

                        <Paragraph style={{ fontSize: 16, color: '#666', marginBottom: 32 }}>
                            Bạn có thể dễ dàng khám phá homestay theo phong cách, vị trí, ngân sách hoặc vibe mong muốn – từ view núi mây ôm Sapa,
                            phố cổ Hội An đầy hoài niệm, cho đến những căn nằm sát biển chỉ cần mở cửa là nghe sóng.
                            BookStay hướng tới một nền tảng gọn – nhanh – trực quan, giúp bạn chọn đúng nơi, đúng cảm xúc, ngay lần đầu tìm kiếm.
                        </Paragraph>

                        <div style={{ display: 'flex', gap: 24, marginTop: 32, flexWrap: 'wrap' }}>
                            <div>
                                <Title level={3} style={{ color: '#1677ff', marginBottom: 4 }}>200K+</Title>
                                <Text style={{ color: '#8c8c8c' }}>Khách hàng tin tưởng</Text>
                            </div>
                            <div>
                                <Title level={3} style={{ color: '#1677ff', marginBottom: 4 }}>5000+</Title>
                                <Text style={{ color: '#8c8c8c' }}>Homestay đối tác</Text>
                            </div>
                            <div>
                                <Title level={3} style={{ color: '#1677ff', marginBottom: 4 }}>63</Title>
                                <Text style={{ color: '#8c8c8c' }}>Tỉnh thành</Text>
                            </div>
                        </div>
                    </Col>
                    <Col xs={24} md={12}>
                        <div style={{ position: 'relative' }}>
                            <img
                                src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85"
                                alt="Về chúng tôi"
                                style={{
                                    width: '100%',
                                    borderRadius: 20,
                                    boxShadow: '0 20px 60px rgba(0,0,0,0.15)',
                                }}
                            />
                            <Card
                                bordered={false}
                                style={{
                                    position: 'absolute',
                                    bottom: -40,
                                    left: -40,
                                    background: '#fff',
                                    borderRadius: 16,
                                    boxShadow: '0 10px 40px rgba(0,0,0,0.15)',
                                    minWidth: 200,
                                }}
                                bodyStyle={{ padding: 24 }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                                    <div
                                        style={{
                                            width: 56,
                                            height: 56,
                                            borderRadius: '50%',
                                            background: '#f0f5ff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                        }}
                                    >
                                        <StarFilled style={{ fontSize: 24, color: '#1677ff' }} />
                                    </div>
                                    <div>
                                        <Title level={4} style={{ margin: 0, marginBottom: 4 }}>4.8/5.0</Title>
                                        <Text type="secondary">Đánh giá trung bình</Text>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </Col>
                </Row>
            </Content>




            {/* CAM KẾT */}
            <Content style={{ padding: "80px 100px" }}>
                <Divider />
                <Title level={2} style={{ textAlign: "center" }}>
                    Cam kết của chúng tôi
                </Title>
                <Paragraph style={{ textAlign: "center", maxWidth: 800, margin: "20px auto", color: "#555" }}>
                    Chúng tôi cam kết mang đến cho bạn trải nghiệm đặt phòng nhanh chóng, an toàn và đáng tin cậy nhất.
                    Mỗi homestay trên nền tảng đều được kiểm duyệt kỹ lưỡng để đảm bảo chất lượng,
                    giúp bạn an tâm tận hưởng kỳ nghỉ tuyệt vời.
                </Paragraph>
            </Content>

            {/* FOOTER */}


            <AppFooter />
        </Layout>
    );
}
