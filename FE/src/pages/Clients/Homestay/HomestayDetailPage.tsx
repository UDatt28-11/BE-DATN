import React, { useState, useMemo } from "react";
import {
    Layout,
    Row,
    Col,
    Typography,
    Card,
    Rate,
    Breadcrumb,
    Button,
    Divider,
    Space,
    Tag,
    Image,
    DatePicker,
    Select,
    Avatar,
    List,
    Affix, // Dùng để "dính" form đặt phòng
} from "antd";
import {
    HomeOutlined,
    StarFilled,
    WifiOutlined,
    CarOutlined, // Biểu tượng Bếp (thay thế)
    UserOutlined,
    CalendarOutlined,
    EnvironmentOutlined,
    CheckCircleFilled,
} from "@ant-design/icons";
import { Link, useParams } from "react-router-dom"; // Import useParams
import type { RangePickerProps } from "antd/es/date-picker";
import type { Dayjs } from "dayjs";

// Import các layout chung
import AppHeader from "../../../components/Layout/AppHeader";
import AppFooter from "../../../components/Layout/AppFooter";

const { Content } = Layout;
const { Title, Text, Paragraph } = Typography;
const { RangePicker } = DatePicker;
const { Option } = Select;

// --- Dữ liệu giả lập (Mock Data) Mở rộng ---
// (Sao chép từ file list và thêm chi tiết)

const allHomestays = [
    {

        id: 1,
        name: "Nhà Vườn Hoa Sen",
        price: 800000,
        rating: 4.8,
        category: "garden_house",
        location: "Xuân Giang, Sóc Sơn",
        description: "Không gian nhà vườn yên bình được bao quanh bởi hồ sen và cây xanh. Phù hợp các nhóm bạn hoặc gia đình thích một buổi nghỉ dưỡng nhẹ nhàng, thoát khỏi ồn ào nội thành.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "Khu vườn rộng" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1613977257363-27618c7c3886?w=800&q=80",
            "https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=800&q=80",
        ],
        reviews: [
            { user: "Thu Hà", rating: 5, comment: "Không gian chill cực, hoa sen nở nhìn mê!" },
            { user: "Quang Minh", rating: 4.5, comment: "Đáng tiền, chủ nhà nhiệt tình." },
        ]
    },
    {
        id: 2,
        name: "Villa Minh Phú Sóc Sơn",
        price: 2500000,
        rating: 4.9,
        category: "villa",
        location: "Minh Phú, Sóc Sơn",
        description: "Villa cao cấp hồ bơi riêng, nội thất hiện đại, sân rộng cho BBQ. Đi nhóm đông cực hợp.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "Hồ bơi riêng" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&q=80",
            "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80",
        ],
        reviews: [
            { user: "Tú Anh", rating: 5, comment: "Bể bơi xịn, view chill, đi team building quá hợp." },
            { user: "Hữu Đức", rating: 4.5, comment: "Phòng đẹp, sạch, view xử lý tốt." },
        ]
    },
    {
        id: 3,
        name: "Bungalow Tre Xanh",
        price: 600000,
        rating: 4.5,
        category: "bungalow",
        location: "Mai Đình, Sóc Sơn",
        description: "Bungalow bằng tre đơn giản, mộc mạc kiểu retreat thiên nhiên.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "Không gian xanh" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=800&q=80",
        ],
        reviews: [
            { user: "Phương Linh", rating: 4.5, comment: "Mộc mạc, dễ thương, đáng thử." },
            { user: "Khánh Duy", rating: 4, comment: "Ổn trong tầm giá." },
        ]
    },
    {
        id: 4,
        name: "Homestay Gỗ Bắc Phú",
        price: 1200000,
        rating: 4.7,
        category: "wooden_house",
        location: "Bắc Phú, Sóc Sơn",
        description: "Nhà gỗ ấm áp phong cách Bắc Âu, phù hợp nhóm từ 8–10 người.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "BBQ ngoài trời" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80",
        ],
        reviews: [
            { user: "Lan", rating: 4.8, comment: "Gỗ thơm, view đẹp, đêm chill lắm." },
            { user: "Phong", rating: 4.6, comment: "Sạch, chủ nhà hỗ trợ ok." },
        ]
    },
    {
        id: 5,
        name: "Villa View Hồ Đầm Vạc",
        price: 3000000,
        rating: 5.0,
        category: "lake_view",
        location: "Đức Hoà, Sóc Sơn",
        description: "Biệt thự view hồ cực đẹp, phù hợp nhóm đông cần chụp ảnh & party.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "View mặt hồ" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800&q=80",
        ],
        reviews: [
            { user: "Mỹ Tiên", rating: 5, comment: "Ảnh ra đẹp như resort quốc tế." },
            { user: "Hải Nam", rating: 5, comment: "Xứng đáng 10/10." },
        ]
    },
    {
        id: 6,
        name: "Nhà Vườn Thanh Xuân",
        price: 750000,
        rating: 4.6,
        category: "garden_house",
        location: "Thanh Xuân, Sóc Sơn",
        description: "Nhà vườn yên tĩnh, nhiều cây, hợp gia đình có trẻ em.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "Vườn rộng" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=800&q=80",
        ],
        reviews: [
            { user: "Hồng Nhung", rating: 4.6, comment: "Nhiều cây xanh dễ chịu." },
            { user: "Đức Tài", rating: 4.5, comment: "Giá ổn, yên bình." },
        ]
    },
    {
        id: 7,
        name: "Biệt Thự Tiến Thắng Resort",
        price: 2800000,
        rating: 4.8,
        category: "villa",
        location: "Tiến Thắng, Sóc Sơn",
        description: "Villa rộng, hồ bơi, sân cỏ, hợp đi team building & chụp ảnh cưới.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "Hồ bơi" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=800&q=80",
        ],
        reviews: [
            { user: "Gia Bảo", rating: 4.8, comment: "Rộng, sạch, chụp ảnh cực chill." },
            { user: "An Khoa", rating: 4.7, comment: "Hợp đi đông." },
        ]
    },
    {
        id: 8,
        name: "Homestay Núi Cầu Bích",
        price: 900000,
        rating: 4.4,
        category: "mountain_view",
        location: "Phú Minh, Sóc Sơn",
        description: "View núi – rất hợp retreat, đọc sách, detox.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "View núi" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80",
        ],
        reviews: [
            { user: "Bảo Châu", rating: 4.4, comment: "Núi gần, không khí mát." },
            { user: "Hoài Phương", rating: 4.3, comment: "Chill, dễ chịu." },
        ]
    },
    {
        id: 9,
        name: "Homestay Núi Cầu Bích",
        price: 900000,
        rating: 4.4,
        category: "mountain_view",
        location: "Phú Minh, Sóc Sơn",
        description: "Cùng khu với id 8 — phong cách nature friendly, rất dễ chịu.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "View núi" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80",
        ],
        reviews: [
            { user: "Lan Anh", rating: 4.4, comment: "Mát mẻ, không khí sạch." },
            { user: "Cường", rating: 4.3, comment: "Ăn BBQ ngoài trời sướng." },
        ]
    },
    {
        id: 10,
        name: "Homestay Núi Cầu Bích",
        price: 900000,
        rating: 4.4,
        category: "mountain_view",
        location: "Phú Minh, Sóc Sơn",
        description: "Phong cách gần núi – thư giãn, thoải mái.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "View núi" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80",
        ],
        reviews: [
            { user: "Hòa Bình", rating: 4.4, comment: "Giá ok, không khí ok." },
            { user: "Uyên", rating: 4.3, comment: "Tĩnh, relax tốt." },
        ]
    },
    {
        id: 11,
        name: "Homestay Núi Cầu Bích",
        price: 900000,
        rating: 4.4,
        category: "mountain_view",
        location: "Phú Minh, Sóc Sơn",
        description: "Dành cho nhóm nhỏ thích retreat, đọc sách, làm việc từ xa.",
        amenities: [
            { icon: <WifiOutlined />, text: "Wifi miễn phí" },
            { icon: <CheckCircleFilled style={{ color: '#52c41a' }} />, text: "View núi" },
            { icon: <CarOutlined />, text: "Bãi đỗ xe" },
        ],
        galleryImages: [
            "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80",
        ],
        reviews: [
            { user: "Kiều My", rating: 4.4, comment: "Rất yên tĩnh." },
            { user: "Thuận", rating: 4.3, comment: "Chill." },
        ]
    },


    // Thêm các homestay khác nếu cần
];
// ------------------------------

// Kiểu trả về cho RangePicker
type RangeValue = RangePickerProps["value"];

const HomestayDetailPage: React.FC = () => {
    const { id } = useParams<{ id: string }>(); // Lấy ID từ URL

    // --- State cho form đặt phòng ---
    const [dates, setDates] = useState<RangeValue>(null);
    const [guests, setGuests] = useState<number>(1);

    // Tìm homestay dựa trên ID
    const homestay = useMemo(() => {
        return allHomestays.find(h => h.id.toString() === id);
    }, [id]);

    // Tính số đêm
    const numNights = useMemo(() => {
        if (!dates || !dates[0] || !dates[1]) {
            return 0;
        }
        return dates[1].diff(dates[0], 'day');
    }, [dates]);

    // Tính tổng tiền
    const totalPrice = useMemo(() => {
        if (!homestay || numNights <= 0) {
            return 0;
        }
        return homestay.price * numNights;
    }, [homestay, numNights]);

    // Xử lý khi không tìm thấy homestay
    if (!homestay) {
        return (
            <Layout style={{ background: "#fff", minHeight: "100vh" }}>
                <AppHeader />
                <Content style={{ padding: "100px 50px", textAlign: "center", marginTop: 70 }}>
                    <Title level={2}>Không tìm thấy homestay</Title>
                    <Paragraph>Homestay bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</Paragraph>
                    <Button type="primary">
                        <Link to="/homestay">Quay lại danh sách</Link>
                    </Button>
                </Content>
                <AppFooter />
            </Layout>
        );
    }

    // Hàm xử lý đặt phòng
    const handleBooking = () => {
        if (numNights <= 0) {
            // Thay thế alert() bằng Modal của Ant Design trong ứng dụng thực tế
            alert("Vui lòng chọn ngày nhận và trả phòng hợp lệ.");
            return;
        }
        console.log("Booking Details:", {
            homestayId: homestay.id,
            dates: dates?.map(date => date?.format("YYYY-MM-DD")),
            guests,
            totalPrice,
        });
        // Thay thế alert() bằng Modal hoặc thông báo thành công
        alert(`Đặt phòng thành công! Tổng cộng: ${totalPrice.toLocaleString("vi-VN")}đ cho ${numNights} đêm.`);
    };

    return (
        <Layout style={{ background: "#fff" }}>
            <AppHeader />

            <Content style={{ padding: "0 50px", marginTop: 70 }}>
                <div style={{ maxWidth: 1200, margin: "0 auto" }}>
                    {/* Breadcrumb */}
                    <Breadcrumb style={{ margin: "24px 0" }}>
                        <Breadcrumb.Item>
                            <Link to="/">
                                <HomeOutlined /> Trang chủ
                            </Link>
                        </Breadcrumb.Item>
                        <Breadcrumb.Item>
                            <Link to="/homestay">Danh sách Homestay</Link>
                        </Breadcrumb.Item>
                        <Breadcrumb.Item>{homestay.name}</Breadcrumb.Item>
                    </Breadcrumb>

                    {/* Tiêu đề và Đánh giá */}
                    <Title level={2} style={{ marginBottom: 8 }}>
                        {homestay.name}
                    </Title>
                    <Space size="middle">
                        <Rate allowHalf disabled defaultValue={homestay.rating} />
                        <Text strong>{homestay.rating} ({homestay.reviews.length} đánh giá)</Text>
                        <Text type="secondary">
                            <EnvironmentOutlined style={{ marginRight: 4 }} />
                            {homestay.location}
                        </Text>
                    </Space>

                    {/* Thư viện ảnh */}
                    <Image.PreviewGroup>
                        <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
                            <Col span={16}>
                                <Image
                                    width="100%"
                                    height={500}
                                    src={homestay.galleryImages[0]}
                                    style={{ objectFit: "cover", borderRadius: 12 }}
                                />
                            </Col>
                            <Col span={8}>
                                <Row gutter={[16, 16]}>
                                    <Col span={24}>
                                        <Image
                                            width="100%"
                                            height={242} // 500 / 2 - (16/2)
                                            src={homestay.galleryImages[1]}
                                            style={{ objectFit: "cover", borderRadius: 12 }}
                                        />
                                    </Col>
                                    <Col span={24}>
                                        <Image
                                            width="100%"
                                            height={242}
                                            src={homestay.galleryImages[2]}
                                            style={{ objectFit: "cover", borderRadius: 12 }}
                                        />
                                    </Col>
                                </Row>
                            </Col>
                        </Row>
                    </Image.PreviewGroup>

                    {/* Nội dung chính và Form Đặt phòng */}
                    <Row gutter={[32, 32]} style={{ marginTop: 32 }}>
                        {/* Cột trái: Thông tin chi tiết */}
                        <Col span={16}>
                            <Title level={4}>Mô tả</Title>
                            <Paragraph style={{ fontSize: 16, lineHeight: 1.8 }}>
                                {homestay.description}
                            </Paragraph>

                            <Divider />

                            {/* Tiện nghi */}
                            <Title level={4}>Tiện nghi</Title>
                            <Row gutter={[16, 16]}>
                                {homestay.amenities.map((item, index) => (
                                    <Col key={index} xs={24} sm={12}>
                                        <Space size="middle">
                                            <span style={{ fontSize: 20, color: '#555' }}>{item.icon}</span>
                                            <Text style={{ fontSize: 16 }}>{item.text}</Text>
                                        </Space>
                                    </Col>
                                ))}
                            </Row>

                            <Divider />

                            {/* Đánh giá */}
                            <Title level={4}>Đánh giá ({homestay.reviews.length})</Title>
                            <List
                                itemLayout="horizontal"
                                dataSource={homestay.reviews}
                                renderItem={(item) => (
                                    <List.Item>
                                        <List.Item.Meta
                                            avatar={<Avatar icon={<UserOutlined />} />}
                                            title={
                                                <Space>
                                                    <Text strong>{item.user}</Text>
                                                    <Rate disabled defaultValue={item.rating} style={{ fontSize: 14 }} />
                                                </Space>
                                            }
                                            description={<Paragraph style={{ marginTop: 4 }}>{item.comment}</Paragraph>}
                                        />
                                    </List.Item>
                                )}
                            />
                        </Col>

                        {/* Cột phải: Form Đặt phòng */}
                        <Col span={8}>
                            {/* Affix giúp form "dính" lại khi cuộn */}
                            <Affix offsetTop={90}>
                                <Card
                                    bordered={false}
                                    style={{
                                        borderRadius: 12,
                                        boxShadow: "0 8px 24px rgba(0,0,0,0.12)",
                                    }}
                                >
                                    <Title level={4}>
                                        <Text strong style={{ color: "#1677ff", fontSize: 28 }}>
                                            {homestay.price.toLocaleString("vi-VN")}đ
                                        </Text>
                                        <Text style={{ fontSize: 16, color: "#555" }}> / đêm</Text>
                                    </Title>

                                    <Divider style={{ margin: "16px 0" }} />

                                    {/* Chọn ngày */}
                                    <Paragraph strong style={{ marginBottom: 8 }}>
                                        <CalendarOutlined style={{ marginRight: 8 }} />
                                        Ngày nhận - trả phòng
                                    </Paragraph>
                                    <RangePicker
                                        style={{ width: "100%" }}
                                        size="large"
                                        onChange={(dates) => setDates(dates)}
                                        format="DD/MM/YYYY"
                                    />

                                    {/* Chọn khách */}
                                    <Paragraph strong style={{ marginTop: 16, marginBottom: 8 }}>
                                        <UserOutlined style={{ marginRight: 8 }} />
                                        Số lượng khách
                                    </Paragraph>
                                    <Select
                                        defaultValue={1}
                                        size="large"
                                        style={{ width: "100%" }}
                                        onChange={(value) => setGuests(value)}
                                    >
                                        {[1, 2, 3, 4, 5, 6, 7, 8].map((num) => (
                                            <Option key={num} value={num}>{num} khách</Option>
                                        ))}
                                    </Select>

                                    {/* Tính tiền (nếu có chọn ngày) */}
                                    {numNights > 0 && (
                                        <>
                                            <Divider style={{ margin: "16px 0" }} />
                                            <Row justify="space-between">
                                                <Text style={{ fontSize: 16 }}>
                                                    {homestay.price.toLocaleString("vi-VN")}đ x {numNights} đêm
                                                </Text>
                                                <Text style={{ fontSize: 16 }}>
                                                    {totalPrice.toLocaleString("vi-VN")}đ
                                                </Text>
                                            </Row>
                                        </>
                                    )}

                                    <Button
                                        type="primary"
                                        size="large"
                                        block
                                        style={{ marginTop: 24, fontWeight: 600 }}
                                        onClick={handleBooking}
                                    >
                                        Đặt ngay
                                    </Button>

                                    <Paragraph style={{ textAlign: 'center', marginTop: 16 }} type="secondary">
                                        Bạn chưa bị trừ tiền
                                    </Paragraph>

                                </Card>
                            </Affix>
                        </Col>
                    </Row>
                </div>
            </Content>

            <AppFooter />
        </Layout>
    );
};

export default HomestayDetailPage;
