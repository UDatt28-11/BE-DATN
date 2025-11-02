import React, { useState, useRef, useEffect } from 'react';
import {
    Layout,
    Row,
    Col,
    Typography,
    Button,
    Card,
    Form,
    DatePicker,
    Input,
    Rate,
    Avatar,
    Statistic,
    Popover,
    Checkbox,
    Space,
    Divider,
} from 'antd';
import {
    SearchOutlined,
    StarFilled,
    EnvironmentOutlined,
    HomeOutlined,
    HeartOutlined,
    RightOutlined,
    TeamOutlined,
    UserOutlined,
    CalendarOutlined,
    MinusOutlined,
    PlusOutlined,
} from '@ant-design/icons';

const { Content } = Layout;
const { Title, Paragraph, Text } = Typography;
const { RangePicker } = DatePicker;

import AppHeader from '../../../components/Layout/AppHeader';
import AppFooter from '../../../components/Layout/AppFooter';

const GuestSelector = () => {
    const [adults, setAdults] = useState(2);
    const [children, setChildren] = useState(0);
    const [rooms, setRooms] = useState(1);
    const [pets, setPets] = useState(false);
    const [visible, setVisible] = useState(false);

    const content = (
        <div style={{ width: 320, padding: 8 }}>
            <div style={{ marginBottom: 20 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Text strong>Người lớn</Text>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Button
                            type="text"
                            icon={<MinusOutlined />}
                            onClick={() => setAdults(Math.max(1, adults - 1))}
                            disabled={adults <= 1}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: adults <= 1 ? '#ccc' : '#1890ff',
                            }}
                        />
                        <Text strong style={{ fontSize: 16, minWidth: 24, textAlign: 'center' }}>{adults}</Text>
                        <Button
                            type="text"
                            icon={<PlusOutlined />}
                            onClick={() => setAdults(adults + 1)}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: '#1890ff',
                            }}
                        />
                    </div>
                </div>
            </div>

            <div style={{ marginBottom: 20 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Text strong>Trẻ em</Text>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Button
                            type="text"
                            icon={<MinusOutlined />}
                            onClick={() => setChildren(Math.max(0, children - 1))}
                            disabled={children <= 0}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: children <= 0 ? '#ccc' : '#1890ff',
                            }}
                        />
                        <Text strong style={{ fontSize: 16, minWidth: 24, textAlign: 'center' }}>{children}</Text>
                        <Button
                            type="text"
                            icon={<PlusOutlined />}
                            onClick={() => setChildren(children + 1)}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: '#1890ff',
                            }}
                        />
                    </div>
                </div>
            </div>

            <div style={{ marginBottom: 20 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Text strong>Phòng</Text>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Button
                            type="text"
                            icon={<MinusOutlined />}
                            onClick={() => setRooms(Math.max(1, rooms - 1))}
                            disabled={rooms <= 1}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: rooms <= 1 ? '#ccc' : '#1890ff',
                            }}
                        />
                        <Text strong style={{ fontSize: 16, minWidth: 24, textAlign: 'center' }}>{rooms}</Text>
                        <Button
                            type="text"
                            icon={<PlusOutlined />}
                            onClick={() => setRooms(rooms + 1)}
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                border: '1px solid #1890ff',
                                color: '#1890ff',
                            }}
                        />
                    </div>
                </div>
            </div>

            <Divider style={{ margin: '12px 0' }} />

            <div style={{ marginBottom: 12 }}>
                <Checkbox checked={pets} onChange={(e) => setPets(e.target.checked)}>
                    <Text>Mang thú cưng đi cùng</Text>
                </Checkbox>
            </div>

            <Button
                type="primary"
                block
                onClick={() => setVisible(false)}
                style={{ marginTop: 16 }}
            >
                Xong
            </Button>
        </div>
    );

    return (
        <Popover
            content={content}
            trigger="click"
            open={visible}
            onOpenChange={setVisible}
            placement="bottomLeft"
        >
            <div
                style={{
                    padding: '16px 20px',
                    height: '100%',
                    cursor: 'pointer',
                    transition: 'background 0.2s',
                }}
                onMouseEnter={(e) => e.currentTarget.style.background = '#f5f5f5'}
                onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                    <UserOutlined style={{ fontSize: 20, color: '#262626' }} />
                </div>
                <Text style={{ fontSize: 16, fontWeight: 500, color: '#262626' }}>
                    {adults} người lớn · {children} trẻ em · {rooms} phòng
                </Text>
            </div>
        </Popover>
    );
};

const HomePage = () => {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [videoLoaded, setVideoLoaded] = useState(false);

    useEffect(() => {
        if (videoRef.current) {
            videoRef.current.playbackRate = 0.6; // Chuyển động chậm
        }
    }, []);

    const destinations = [
        { name: 'Studio view đồi thông', count: '20 homestay', img: 'https://images.unsplash.com/photo-1583417319070-4a69db38a482' },
        { name: 'Villa hồ bơi riêng', count: '6 homestay', img: 'https://images.unsplash.com/photo-1583221234656-5e0a85305de9' },
        { name: 'Cabin gỗ trên cao', count: '30 homestay', img: 'https://images.unsplash.com/photo-1559827260-dc66d52bef19' },
        { name: 'Căn hộ 1 phòng ngủ', count: '20 homestay', img: 'https://images.unsplash.com/photo-1569154941061-e231b4725ef1' },
        { name: 'Bungalow ', count: '25 homestay', img: 'https://images.unsplash.com/photo-1583417319070-4a69db38a482' },
        { name: 'Phòng dorm giá rẻ', count: '45 homestay', img: 'https://images.unsplash.com/photo-1505228395891-9a51e7e86bf6' },
    ];

    const featuredHomestays = [
        {
            name: 'Mây Homestay Đà Lạt',
            img: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b',
            price: '900.000đ',
            rating: 4.8,
            reviews: 256,
            location: 'Đà Lạt',
        },
        {
            name: 'The Chill House Sapa',
            img: 'https://images.unsplash.com/photo-1554995207-c18c203602cb',
            price: '1.200.000đ',
            rating: 4.9,
            reviews: 189,
            location: 'Sapa',
        },
        {
            name: 'Santorini Villa Phú Quốc',
            img: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c',
            price: '2.000.000đ',
            rating: 5.0,
            reviews: 324,
            location: 'Phú Quốc',
        },
        {
            name: 'Làng Gió Biển Nha Trang',
            img: 'https://images.unsplash.com/photo-1600585154154-66c2c6b8b46c',
            price: '1.400.000đ',
            rating: 4.7,
            reviews: 198,
            location: 'Nha Trang',
        },
    ];

    const onFinish = (values) => {
        console.log('Booking form values:', values);
    };

    return (
        <Layout style={{ background: '#fff' }}>
            <AppHeader />

            {/* VIDEO HERO BANNER */}
            <div style={{ marginTop: 64, position: 'relative', overflow: 'hidden' }}>
                {/* Video Background */}
                <video
                    ref={videoRef}
                    autoPlay
                    loop
                    muted
                    playsInline
                    onLoadedData={() => setVideoLoaded(true)}
                    style={{
                        width: '100%',
                        height: '85vh',
                        objectFit: 'cover',
                        display: 'block',
                    }}
                >
                    {/* Sử dụng video miễn phí từ Pexels */}
                    <source src="https://res.cloudinary.com/dzazpiela/video/upload/v1762077662/Hailuo_Video_animate_an_image_441329764660391937_tpwteg.mp4" type="video/mp4" />
                    Your browser does not support the video tag.
                </video>

                {/* Overlay */}
                <div
                    style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        background: 'linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5))',
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
                                fontSize: 64,
                                fontWeight: 800,
                                marginBottom: 24,
                                textShadow: '0 4px 20px rgba(0,0,0,0.8)',
                                animation: 'fadeInUp 1s ease-out',
                            }}
                        >
                            Trải Nghiệm Kỳ Nghỉ Đáng Nhớ
                        </Title>
                        <Paragraph
                            style={{
                                color: '#fff',
                                fontSize: 22,
                                marginBottom: 40,
                                textShadow: '0 2px 10px rgba(0,0,0,0.8)',
                                animation: 'fadeInUp 1.2s ease-out',
                            }}
                        >
                            Khám phá những homestay tuyệt vời trên khắp Việt Nam
                        </Paragraph>
                        <Button
                            type="primary"
                            size="large"
                            style={{
                                height: 56,
                                fontSize: 18,
                                fontWeight: 600,
                                paddingLeft: 48,
                                paddingRight: 48,
                                borderRadius: 8,
                                animation: 'fadeInUp 1.4s ease-out',
                            }}
                        >
                            Khám phá ngay <RightOutlined />
                        </Button>
                    </div>
                </div>
            </div>

            {/* BOOKING FORM */}
            <Content style={{ marginTop: '-100px', padding: '0 50px', position: 'relative', zIndex: 10 }}>
                <Card
                    style={{
                        maxWidth: 1400,
                        margin: '0 auto',
                        borderRadius: 8,
                        boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
                        border: '3px solid #FFB700',
                        padding: 0,
                    }}
                >
                    <Form layout="vertical" onFinish={onFinish}>
                        <Row gutter={0} style={{ alignItems: 'stretch' }}>
                            <Col xs={24} sm={24} md={8} style={{ borderRight: '1px solid #e0e0e0' }}>
                                <div style={{ padding: '16px 20px', height: '100%' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                        <HomeOutlined style={{ fontSize: 20, color: '#262626' }} />
                                        <Text style={{ fontSize: 13, color: '#262626' }}>Điểm đến</Text>
                                    </div>
                                    <Form.Item name="destination" style={{ marginBottom: 0 }}>
                                        <Input
                                            placeholder="Bạn muốn đi đâu?"
                                            size="large"
                                            bordered={false}
                                            style={{
                                                fontSize: 16,
                                                fontWeight: 500,
                                                padding: '4px 0',
                                            }}
                                        />
                                    </Form.Item>
                                </div>
                            </Col>

                            <Col xs={24} sm={24} md={8} style={{ borderRight: '1px solid #e0e0e0' }}>
                                <div style={{ padding: '16px 20px', height: '100%' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                        <CalendarOutlined style={{ fontSize: 20, color: '#262626' }} />
                                    </div>
                                    <Form.Item name="dateRange" style={{ marginBottom: 0 }}>
                                        <RangePicker
                                            placeholder={['Nhận phòng', 'Trả phòng']}
                                            size="large"
                                            bordered={false}
                                            separator="—"
                                            style={{
                                                width: '100%',
                                                fontSize: 16,
                                                fontWeight: 500,
                                                padding: 0,
                                            }}
                                            format="DD/MM/YYYY"
                                        />
                                    </Form.Item>
                                </div>
                            </Col>

                            <Col xs={24} sm={24} md={5} style={{ borderRight: '1px solid #e0e0e0' }}>
                                <GuestSelector />
                            </Col>

                            <Col xs={24} sm={24} md={3}>
                                <Form.Item style={{ marginBottom: 0, height: '100%' }}>
                                    <Button
                                        type="primary"
                                        htmlType="submit"
                                        size="large"
                                        block
                                        style={{
                                            fontWeight: 600,
                                            height: '100%',
                                            minHeight: 80,
                                            fontSize: 16,
                                            borderRadius: 0,
                                            borderTopRightRadius: 6,
                                            borderBottomRightRadius: 6,
                                        }}
                                    >
                                        Tìm
                                    </Button>
                                </Form.Item>
                            </Col>
                        </Row>
                    </Form>
                </Card>
            </Content>

            {/* STATISTICS */}
            <Content style={{ padding: '80px 50px', background: '#fff' }}>
                <Row gutter={[48, 48]} justify="center" style={{ maxWidth: 1200, margin: '0 auto' }}>
                    {[
                        { title: 'Homestay', value: '100', icon: <HomeOutlined /> },
                        { title: 'Khách hàng', value: '5K', icon: <TeamOutlined /> },
                        { title: 'Tỉnh thành', value: 'Hà Nội', icon: <EnvironmentOutlined /> },
                        { title: 'Đánh giá 5⭐', value: '1k', icon: <StarFilled /> },
                    ].map((stat, index) => (
                        <Col key={index} xs={12} sm={6}>
                            <div style={{ textAlign: 'center' }}>
                                <div style={{ fontSize: 48, color: '#1890ff', marginBottom: 12 }}>
                                    {stat.icon}
                                </div>
                                <Statistic
                                    value={stat.value}
                                    valueStyle={{ fontSize: 36, fontWeight: 700, color: '#262626' }}
                                />
                                <Text style={{ fontSize: 16, color: '#595959' }}>{stat.title}</Text>
                            </div>
                        </Col>
                    ))}
                </Row>
            </Content>

            {/* POPULAR DESTINATIONS */}
            <Content style={{ padding: '80px 50px', background: '#fafafa' }}>
                <Title level={2} style={{ textAlign: 'center', marginBottom: 16, fontSize: 42, fontWeight: 700 }}>
                    Loại phòng phổ biến
                </Title>
                <Paragraph style={{ textAlign: 'center', fontSize: 18, color: '#666', marginBottom: 64 }}>
                    Khám phá những căn phòng được yêu thích nhất
                </Paragraph>
                <Row gutter={[24, 24]} justify="center" style={{ maxWidth: 1200, margin: '0 auto' }}>
                    {destinations.map((dest, index) => (
                        <Col key={index} xs={24} sm={12} md={8}>
                            <Card
                                hoverable
                                bordered={false}
                                cover={
                                    <div style={{ position: 'relative', overflow: 'hidden', height: 280 }}>
                                        <img
                                            src={dest.img}
                                            alt={dest.name}
                                            style={{
                                                width: '100%',
                                                height: '100%',
                                                objectFit: 'cover',
                                                transition: 'transform 0.4s',
                                            }}
                                            onMouseOver={(e) => (e.currentTarget.style.transform = 'scale(1.1)')}
                                            onMouseOut={(e) => (e.currentTarget.style.transform = 'scale(1)')}
                                        />
                                        <div
                                            style={{
                                                position: 'absolute',
                                                top: 0,
                                                left: 0,
                                                right: 0,
                                                bottom: 0,
                                                background: 'linear-gradient(to bottom, transparent, rgba(0,0,0,0.7))',
                                                display: 'flex',
                                                flexDirection: 'column',
                                                justifyContent: 'flex-end',
                                                padding: 24,
                                            }}
                                        >
                                            <Title level={3} style={{ color: '#fff', margin: 0, marginBottom: 8 }}>
                                                {dest.name}
                                            </Title>
                                            <Text style={{ color: '#fff', fontSize: 15 }}>{dest.count}</Text>
                                        </div>
                                    </div>
                                }
                                style={{ borderRadius: 16, overflow: 'hidden' }}
                            />
                        </Col>
                    ))}
                </Row>
            </Content>

            {/* FEATURED HOMESTAYS */}
            <Content style={{ padding: '80px 50px', background: '#fff' }}>
                <Title level={2} style={{ textAlign: 'center', marginBottom: 16, fontSize: 42, fontWeight: 700 }}>
                    Homestay nổi bật
                </Title>
                <Paragraph style={{ textAlign: 'center', fontSize: 18, color: '#666', marginBottom: 64 }}>
                    Những lựa chọn được yêu thích nhất từ khách hàng
                </Paragraph>
                <Row gutter={[24, 24]} justify="center" style={{ maxWidth: 1200, margin: '0 auto' }}>
                    {featuredHomestays.map((item, index) => (
                        <Col key={index} xs={24} sm={12} md={6}>
                            <Card
                                hoverable
                                bordered={false}
                                cover={
                                    <div style={{ position: 'relative', overflow: 'hidden', height: 240 }}>
                                        <img
                                            src={item.img}
                                            alt={item.name}
                                            style={{
                                                width: '100%',
                                                height: '100%',
                                                objectFit: 'cover',
                                                transition: 'transform 0.3s',
                                            }}
                                            onMouseOver={(e) => (e.currentTarget.style.transform = 'scale(1.1)')}
                                            onMouseOut={(e) => (e.currentTarget.style.transform = 'scale(1)')}
                                        />
                                        <Button
                                            type="text"
                                            icon={<HeartOutlined />}
                                            style={{
                                                position: 'absolute',
                                                top: 12,
                                                right: 12,
                                                background: 'rgba(255,255,255,0.9)',
                                                borderRadius: '50%',
                                                width: 40,
                                                height: 40,
                                            }}
                                        />
                                    </div>
                                }
                                style={{ borderRadius: 16, overflow: 'hidden' }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginBottom: 8 }}>
                                    <StarFilled style={{ color: '#faad14' }} />
                                    <Text strong style={{ fontSize: 15 }}>{item.rating}</Text>
                                    <Text type="secondary" style={{ fontSize: 14 }}>({item.reviews})</Text>
                                </div>
                                <Title level={5} style={{ marginBottom: 8, fontWeight: 600 }}>
                                    {item.name}
                                </Title>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginBottom: 12 }}>
                                    <EnvironmentOutlined style={{ color: '#666' }} />
                                    <Text type="secondary" style={{ fontSize: 14 }}>{item.location}</Text>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div>
                                        <Text strong style={{ fontSize: 18, color: '#1890ff' }}>{item.price}</Text>
                                        <Text type="secondary" style={{ fontSize: 14 }}>/đêm</Text>
                                    </div>
                                    <Button type="primary" size="small">Đặt ngay</Button>
                                </div>
                            </Card>
                        </Col>
                    ))}
                </Row>
            </Content>

            {/* HOMESTAY ĐỘC ĐÁO TẠI HÀ NỘI */}
            <Content style={{ padding: '80px 50px', background: '#fff' }}>
                <div style={{ maxWidth: 1200, margin: '0 auto' }}>
                    <Title level={2} style={{ marginBottom: 8, fontSize: 28, fontWeight: 700 }}>
                        Homestay độc đáo tại BookStay
                    </Title>
                    <Paragraph style={{ fontSize: 16, color: '#666', marginBottom: 32 }}>
                        Những không gian lưu trú mang dấu ấn riêng – từ góc phố yên bình đến view hồ lộng gió.                    </Paragraph>

                    <Row gutter={[16, 16]}>
                        {[
                            {
                                name: 'Hà Nội La Siesta Hotel & Spa',
                                location: 'Hoàn Kiếm, Hà Nội',
                                rating: 4,
                                reviews: 2854,
                                price: '1.200.000đ',
                                img: 'https://images.unsplash.com/photo-1566073771259-6a8506099945',
                                badge: 'Xuất sắc',
                            },
                            {
                                name: 'Hanoi Backstreet Hotel',
                                location: 'Phố Cổ, Hà Nội',
                                rating: 4.2,
                                reviews: 1567,
                                price: '850.000đ',
                                img: 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4',
                                badge: 'Xuất sắc',
                            },
                            {
                                name: 'Tây Hồ Cozy Homestay',
                                location: 'Tây Hồ, Hà Nội',
                                rating: 4.9,
                                reviews: 892,
                                price: '950.000đ',
                                img: 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267',
                                badge: 'Xuất sắc',
                            },
                            {
                                name: 'Old Quarter View Hotel',
                                location: 'Hoàn Kiếm, Hà Nội',
                                rating: 4.8,
                                reviews: 1234,
                                price: '1.100.000đ',
                                img: 'https://images.unsplash.com/photo-1571896349842-33c89424de2d',
                                badge: 'Tuyệt vời',
                            },
                        ].map((item, index) => (
                            <Col key={index} xs={24} sm={12} md={6}>
                                <Card
                                    bordered={false}
                                    hoverable
                                    style={{ borderRadius: 12, overflow: "hidden" }}
                                    cover={
                                        <div style={{ position: "relative", height: 200 }}>
                                            <img
                                                src={item.img}
                                                style={{ width: "100%", height: "100%", objectFit: "cover" }}
                                                alt={item.name}
                                            />

                                            {/* BADGE */}
                                            <div
                                                style={{
                                                    position: "absolute",
                                                    top: 8,
                                                    right: 8,
                                                    background: "#1677ff",
                                                    color: "#fff",
                                                    padding: "4px 10px",
                                                    fontSize: 13,
                                                    borderRadius: 6,
                                                    fontWeight: 600,
                                                    zIndex: 10,
                                                }}
                                            >
                                                {item.badge}
                                            </div>
                                        </div>
                                    }
                                >
                                    {/* RATING */}
                                    <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 6 }}>
                                        <StarFilled style={{ color: "#faad14" }} />
                                        <Text strong>{item.rating}</Text>
                                        <Text type="secondary">({item.reviews})</Text>
                                    </div>

                                    <Title level={5} style={{ margin: 0 }}>
                                        {item.name}
                                    </Title>

                                    <div style={{ margin: "6px 0" }}>
                                        <EnvironmentOutlined style={{ marginRight: 4 }} />
                                        <Text type="secondary">{item.location}</Text>
                                    </div>

                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                                        <Text strong style={{ fontSize: 18, color: "#1677ff" }}>
                                            {item.price}/đêm
                                        </Text>

                                        <Button type="primary" size="small">
                                            Đặt ngay
                                        </Button>
                                    </div>
                                </Card>

                            </Col>
                        ))}
                    </Row>
                </div>
            </Content>


            {/* NHÀ Ở MÀ KHÁCH YÊU THÍCH */}
            <Content style={{ padding: '80px 50px', background: '#f5f5f5' }}>
                <div style={{ maxWidth: 1200, margin: '0 auto' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 32 }}>
                        <Title level={2} style={{ marginBottom: 0, fontSize: 28, fontWeight: 700 }}>
                            HomeStay khách hàng yêu thích nhiều nhất
                        </Title>
                        <Button type="link" style={{ fontSize: 15, fontWeight: 500 }}>
                            Tìm các chỗ nghỉ ở nhà →
                        </Button>
                    </div>

                    <Row gutter={[16, 16]}>
                        {[
                            {
                                name: 'Lemon Lackie',
                                location: 'Stamford',
                                rating: 4.8,
                                reviews: 746,
                                price: 'VND 3.987.140',
                                img: 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688',
                                badge: 'Tuyệt hảo',
                            },
                            {
                                name: 'Aparthotel Stare Miasto',
                                location: 'Old Town',
                                rating: 4.5,
                                reviews: 3207,
                                price: 'VND 2.874.632',
                                img: 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267',
                                badge: 'Tuyệt vời',
                            },
                            {
                                name: '7Seasons Apartments Budapest',
                                location: 'Belváros-Lipótváros',
                                rating: 4.5,
                                reviews: 12785,
                                price: 'VND 3.655.601',
                                img: 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2',
                                badge: 'Tuyệt vời',
                            },
                            {
                                name: '3 Epoques Apartments by Adrez',
                                location: 'Old Town',
                                rating: 4.4,
                                reviews: 367,
                                price: 'VND 2.272.178',
                                img: 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c',
                                badge: 'Tuyệt hảo',
                            },
                        ].map((item, index) => (
                            <Col key={index} xs={24} sm={12} md={6}>
                                <Card
                                    bordered={false}
                                    hoverable
                                    style={{ borderRadius: 12, overflow: "hidden" }}
                                    cover={
                                        <div style={{ position: "relative", height: 200 }}>
                                            <img
                                                src={item.img}
                                                style={{ width: "100%", height: "100%", objectFit: "cover" }}
                                                alt={item.name}
                                            />

                                            {/* BADGE */}
                                            <div
                                                style={{
                                                    position: "absolute",
                                                    top: 8,
                                                    right: 8,
                                                    background: "#1677ff",
                                                    color: "#fff",
                                                    padding: "4px 10px",
                                                    fontSize: 13,
                                                    borderRadius: 6,
                                                    fontWeight: 600,
                                                    zIndex: 10,
                                                }}
                                            >
                                                {item.badge}
                                            </div>
                                        </div>
                                    }
                                >
                                    {/* RATING */}
                                    <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 6 }}>
                                        <StarFilled style={{ color: "#faad14" }} />
                                        <Text strong>{item.rating}</Text>
                                        <Text type="secondary">({item.reviews})</Text>
                                    </div>

                                    <Title level={5} style={{ margin: 0 }}>
                                        {item.name}
                                    </Title>

                                    <div style={{ margin: "6px 0" }}>
                                        <EnvironmentOutlined style={{ marginRight: 4 }} />
                                        <Text type="secondary">{item.location}</Text>
                                    </div>

                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                                        <Text strong style={{ fontSize: 18, color: "#1677ff" }}>
                                            {item.price}/đêm
                                        </Text>

                                        <Button type="primary" size="small">
                                            Đặt ngay
                                        </Button>
                                    </div>
                                </Card>

                            </Col>
                        ))}
                    </Row>
                </div>
            </Content>

            {/* VÌ SAO CHỌN BOOKING.COM */}
            <Content style={{ padding: '80px 50px', background: '#fff' }}>
                <div style={{ maxWidth: 1200, margin: '0 auto' }}>
                    <Title level={2} style={{ marginBottom: 48, fontSize: 28, fontWeight: 700 }}>
                        Vì sao lại chọn BookStay?
                    </Title>

                    <Row gutter={[48, 48]}>
                        {[
                            {
                                icon: '✓',
                                title: 'Dễ đặt, nhanh và... thành công!',
                                desc: 'Booking.com sẽ thực hiện theo yêu cầu đặt phòng của bạn. Bạn chỉ cần đến nhận phòng và tận hưởng kỳ nghỉ thôi!',
                                color: '#0071c2',
                            },
                            {
                                icon: '★',
                                title: 'Hơn 1k đánh giá từ khách hàng thực',
                                desc: 'Khách hàng tin tưởng vào chúng tôi khi họ đi du lịch. Đọc đánh giá để chọn nơi ở tốt nhất cho bạn',
                                color: '#febb02',
                            },
                            {
                                icon: '☆',
                                title: 'Hơn 100 chỗ nghỉ hoàn hảo toàn cầu',
                                desc: 'Từ nhà trọ và biệt thự đến khách sạn, nhà nghỉ và hơn thế nữa - chúng tôi có nơi ở dành cho bạn',
                                color: '#ff8000',
                            },
                            {
                                icon: '♥',
                                title: 'Dịch vụ khách hàng 24/7 bằng 40+ ngôn ngữ',
                                desc: 'Chúng tôi sẵn sàng hỗ trợ bạn bất cứ lúc nào, bất cứ nơi đâu, với hơn 40 ngôn ngữ khác nhau',
                                color: '#d4111e',
                            },
                        ].map((item, index) => (
                            <Col key={index} xs={24} sm={12}>
                                <div style={{ display: 'flex', gap: 20 }}>
                                    <div
                                        style={{
                                            width: 48,
                                            height: 48,
                                            borderRadius: '50%',
                                            background: item.color,
                                            color: '#fff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: 24,
                                            fontWeight: 700,
                                            flexShrink: 0,
                                        }}
                                    >
                                        {item.icon}
                                    </div>
                                    <div>
                                        <Title level={4} style={{ marginBottom: 12, fontWeight: 600, fontSize: 18 }}>
                                            {item.title}
                                        </Title>
                                        <Paragraph style={{ color: '#666', fontSize: 15, lineHeight: 1.6, margin: 0 }}>
                                            {item.desc}
                                        </Paragraph>
                                    </div>
                                </div>
                            </Col>
                        ))}
                    </Row>
                </div>
            </Content>

            {/* CUSTOMER REVIEWS */}
            <Content style={{ padding: '80px 50px', background: '#fafafa' }}>
                <Title level={2} style={{ textAlign: 'center', marginBottom: 16, fontSize: 42, fontWeight: 700 }}>
                    Khách hàng nói gì về chúng tôi
                </Title>
                <Paragraph style={{ textAlign: 'center', fontSize: 18, color: '#666', marginBottom: 64 }}>
                    Hơn 10.000 đánh giá 5 sao từ khách hàng hài lòng
                </Paragraph>
                <Row gutter={[24, 24]} justify="center" style={{ maxWidth: 1200, margin: '0 auto' }}>
                    {[
                        {
                            name: 'Nguyễn Minh Anh',
                            review:
                                'Homestay sạch sẽ, view siêu đẹp, nhân viên hỗ trợ rất nhiệt tình. Giá cả hợp lý, rất đáng tiền! Tôi sẽ quay lại lần nữa.',
                            rating: 5,
                            avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Minh',
                            location: 'Đà Lạt',
                        },
                        {
                            name: 'Trần Tuấn Kiệt',
                            review:
                                'Trải nghiệm tuyệt vời! Website dễ sử dụng, tìm được homestay ưng ý nhanh chóng. Đặt phòng đơn giản, thanh toán an toàn.',
                            rating: 5,
                            avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Tuan',
                            location: 'Sapa',
                        },
                        {
                            name: 'Lê Lan Hương',
                            review:
                                'Mình rất thích giao diện website, nhiều lựa chọn và giá cả minh bạch. Dịch vụ chăm sóc khách hàng tuyệt vời. Highly recommended!',
                            rating: 5,
                            avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Lan',
                            location: 'Nha Trang',
                        },
                    ].map((item, index) => (
                        <Col key={index} xs={24} sm={12} md={8}>
                            <Card
                                bordered={false}
                                style={{
                                    height: '100%',
                                    borderRadius: 16,
                                    boxShadow: '0 4px 16px rgba(0,0,0,0.08)',
                                }}
                                bodyStyle={{ padding: 32 }}
                            >
                                <Rate disabled defaultValue={item.rating} style={{ fontSize: 20, marginBottom: 20 }} />
                                <Paragraph
                                    style={{
                                        fontStyle: 'italic',
                                        fontSize: 16,
                                        lineHeight: 1.8,
                                        marginBottom: 24,
                                        color: '#262626',
                                    }}
                                >
                                    "{item.review}"
                                </Paragraph>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                                    <Avatar src={item.avatar} size={48} />
                                    <div>
                                        <Text strong style={{ fontSize: 16 }}>{item.name}</Text>
                                        <br />
                                        <Text type="secondary" style={{ fontSize: 14 }}>{item.location}</Text>
                                    </div>
                                </div>
                            </Card>
                        </Col>
                    ))}
                </Row>
            </Content>

            <AppFooter />

            {/* CSS Animation */}
            <style>{`
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `}</style>
        </Layout>
    );
};

export default HomePage;