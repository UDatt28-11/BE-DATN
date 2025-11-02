import React, { useState, useMemo } from "react";
import {
    Layout,
    Row,
    Col,
    Typography,
    Card,
    Rate,
    Pagination,
    Breadcrumb,
    Checkbox,
    Slider,
    Input,
    Space,
    Tag,
    Button,
    Drawer,
} from "antd";
import {
    HomeOutlined,
    SearchOutlined,
    DollarCircleOutlined,
    AppstoreOutlined,
    EnvironmentOutlined,
    HeartOutlined,
    FilterOutlined,
    UserOutlined,
    CloseOutlined,
} from "@ant-design/icons";
import { Link } from "react-router-dom";
import type { CheckboxValueType } from "antd/es/checkbox/Group";
import type { SliderSingleProps } from "antd/es/slider";

import AppHeader from "../../../components/Layout/AppHeader";
import AppFooter from "../../../components/Layout/AppFooter";

const { Content } = Layout;
const { Title, Text } = Typography;

// Dữ liệu danh mục đơn giản
const categories = [
    { label: "Nhà vườn", value: "garden_house" },
    { label: "Biệt thự", value: "villa" },
    { label: "Bungalow", value: "bungalow" },
    { label: "Nhà gỗ", value: "wooden_house" },
    { label: "View hồ", value: "lake_view" },
    { label: "View núi", value: "mountain_view" },
];

// Danh sách homestay
const allHomestays = [
    {
        id: 1,
        name: "Nhà Vườn Hoa Sen",
        location: "Xuân Giang, Sóc Sơn",
        price: 800000,
        rating: 4.8,
        reviews: 156,
        category: "garden_house",
        image: "https://images.unsplash.com/photo-1613977257363-27618c7c3886?w=600&q=80",
        capacity: "6-8 người",
    },
    {
        id: 2,
        name: "Villa Minh Phú Sóc Sơn",
        location: "Minh Phú, Sóc Sơn",
        price: 2500000,
        rating: 4.9,
        reviews: 203,
        category: "villa",
        image: "https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&q=80",
        capacity: "10-12 người",
    },
    {
        id: 3,
        name: "Bungalow Tre Xanh",
        location: "Mai Đình, Sóc Sơn",
        price: 600000,
        rating: 4.5,
        reviews: 89,
        category: "bungalow",
        image: "https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=600&q=80",
        capacity: "4-6 người",
    },
    {
        id: 4,
        name: "Homestay Gỗ Bắc Phú",
        location: "Bắc Phú, Sóc Sơn",
        price: 1200000,
        rating: 4.7,
        reviews: 134,
        category: "wooden_house",
        image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80",
        capacity: "8-10 người",
    },
    {
        id: 5,
        name: "Villa View Hồ Đầm Vạc",
        location: "Đức Hoà, Sóc Sơn",
        price: 3000000,
        rating: 5.0,
        reviews: 287,
        category: "lake_view",
        image: "https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=600&q=80",
        capacity: "12-15 người",
    },
    {
        id: 6,
        name: "Nhà Vườn Thanh Xuân",
        location: "Thanh Xuân, Sóc Sơn",
        price: 750000,
        rating: 4.6,
        reviews: 98,
        category: "garden_house",
        image: "https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=600&q=80",
        capacity: "6-8 người",
    },
    {
        id: 7,
        name: "Biệt Thú Tiến Thắng Resort",
        location: "Tiến Thắng, Sóc Sơn",
        price: 2800000,
        rating: 4.8,
        reviews: 176,
        category: "villa",
        image: "https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=600&q=80",
        capacity: "10-12 người",
    },
    {
        id: 8,
        name: "Homestay Núi Cầu Bích",
        location: "Phú Minh, Sóc Sơn",
        price: 900000,
        rating: 4.4,
        reviews: 67,
        category: "mountain_view",
        image: "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=600&q=80",
        capacity: "6-8 người",
    },
    {
        id: 9,
        name: "Homestay Núi Cầu Bích",
        location: "Phú Minh, Sóc Sơn",
        price: 900000,
        rating: 4.4,
        reviews: 67,
        category: "mountain_view",
        image: "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=600&q=80",
        capacity: "6-8 người",
    },
    {
        id: 10,
        name: "Homestay Núi Cầu Bích",
        location: "Phú Minh, Sóc Sơn",
        price: 900000,
        rating: 4.4,
        reviews: 67,
        category: "mountain_view",
        image: "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=600&q=80",
        capacity: "6-8 người",
    },
    {
        id: 11,
        name: "Homestay Núi Cầu Bích",
        location: "Phú Minh, Sóc Sơn",
        price: 900000,
        rating: 4.4,
        reviews: 67,
        category: "mountain_view",
        image: "https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=600&q=80",
        capacity: "6-8 người",
    },
];

const PAGE_SIZE = 9;

const HomestayListPage: React.FC = () => {
    const [selectedCategories, setSelectedCategories] = useState<CheckboxValueType[]>([]);
    const [searchTerm, setSearchTerm] = useState("");
    const [priceRange, setPriceRange] = useState<[number, number]>([0, 3000000]);
    const [currentPage, setCurrentPage] = useState(1);
    const [drawerVisible, setDrawerVisible] = useState(false);

    const formatter: SliderSingleProps["formatter"] = (value) => {
        return `${Number(value).toLocaleString("vi-VN")}đ`;
    };

    // Logic lọc
    const filteredHomestays = useMemo(() => {
        let items = allHomestays;

        if (searchTerm) {
            items = items.filter((item) =>
                item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.location.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        if (selectedCategories.length > 0) {
            items = items.filter((item) =>
                selectedCategories.includes(item.category)
            );
        }

        items = items.filter(
            (item) => item.price >= priceRange[0] && item.price <= priceRange[1]
        );

        return items;
    }, [selectedCategories, searchTerm, priceRange]);

    const paginatedHomestays = useMemo(() => {
        const startIndex = (currentPage - 1) * PAGE_SIZE;
        return filteredHomestays.slice(startIndex, startIndex + PAGE_SIZE);
    }, [filteredHomestays, currentPage]);

    const activeFiltersCount = selectedCategories.length + (searchTerm ? 1 : 0);

    // Component bộ lọc
    const FilterContent = () => (
        <div>
            {/* Tìm kiếm */}
            <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                <div>
                    <Text strong style={{ fontSize: 15, marginBottom: 8, display: 'block' }}>
                        Tìm kiếm
                    </Text>
                    <Input
                        size="large"
                        placeholder="Nhập tên hoặc địa điểm..."
                        prefix={<SearchOutlined />}
                        allowClear
                        value={searchTerm}
                        onChange={(e) => {
                            setSearchTerm(e.target.value);
                            setCurrentPage(1);
                        }}
                    />
                </div>

                {/* Loại hình */}
                <div>
                    <Text strong style={{ fontSize: 15, marginBottom: 8, display: 'block' }}>
                        Loại hình
                    </Text>
                    <Checkbox.Group
                        style={{ width: '100%' }}
                        value={selectedCategories}
                        onChange={(values) => {
                            setSelectedCategories(values);
                            setCurrentPage(1);
                        }}
                    >
                        <Space direction="vertical" style={{ width: '100%' }}>
                            {categories.map((cat) => (
                                <Checkbox
                                    key={cat.value}
                                    value={cat.value}
                                    style={{ fontSize: 14 }}
                                >
                                    {cat.label}
                                </Checkbox>
                            ))}
                        </Space>
                    </Checkbox.Group>
                </div>

                {/* Khoảng giá */}
                <div>
                    <Text strong style={{ fontSize: 15, marginBottom: 12, display: 'block' }}>
                        Khoảng giá (/đêm)
                    </Text>
                    <Slider
                        range
                        min={0}
                        max={3000000}
                        step={100000}
                        value={priceRange}
                        tooltip={{ formatter }}
                        onChange={(value) => {
                            setPriceRange(value);
                            setCurrentPage(1);
                        }}
                    />
                    <Row justify="space-between" style={{ marginTop: 8 }}>
                        <Text type="secondary" style={{ fontSize: 13 }}>
                            {formatter(priceRange[0])}
                        </Text>
                        <Text type="secondary" style={{ fontSize: 13 }}>
                            {formatter(priceRange[1])}
                        </Text>
                    </Row>
                </div>

                {/* Nút xóa bộ lọc */}
                {activeFiltersCount > 0 && (
                    <Button
                        block
                        icon={<CloseOutlined />}
                        onClick={() => {
                            setSelectedCategories([]);
                            setSearchTerm("");
                            setPriceRange([0, 3000000]);
                            setCurrentPage(1);
                        }}
                    >
                        Xóa bộ lọc ({activeFiltersCount})
                    </Button>
                )}
            </Space>
        </div>
    );

    return (
        <Layout style={{ minHeight: '100vh', background: '#f0f2f5' }}>
            <AppHeader />

            <Layout style={{ background: '#f0f2f5', marginTop: 64 }}>
                <Row style={{ maxWidth: 1400, margin: '0 auto', width: '100%', padding: '0 16px' }}>
                    {/* Sidebar - Desktop */}
                    <Col xs={0} lg={6} style={{ padding: '24px 12px 24px 0' }}>
                        <div style={{
                            background: '#fff',
                            borderRadius: 8,
                            padding: 20,
                            position: 'sticky',
                            top: 88,
                        }}>
                            <Title level={4} style={{ marginBottom: 20 }}>
                                <FilterOutlined /> Bộ lọc
                            </Title>
                            <FilterContent />
                        </div>
                    </Col>

                    {/* Main content */}
                    <Col xs={24} lg={18} style={{ padding: '24px 0 24px 12px' }}>
                        {/* Button bộ lọc mobile */}
                        <Button
                            size="large"
                            icon={<FilterOutlined />}
                            onClick={() => setDrawerVisible(true)}
                            style={{
                                marginBottom: 16,
                                display: 'none',
                            }}
                            className="filter-mobile-btn"
                        >
                            Bộ lọc {activeFiltersCount > 0 && `(${activeFiltersCount})`}
                        </Button>

                        {/* Breadcrumb */}
                        <Breadcrumb style={{ marginBottom: 16 }}>
                            <Breadcrumb.Item>
                                <Link to="/">
                                    <HomeOutlined /> Trang chủ
                                </Link>
                            </Breadcrumb.Item>
                            <Breadcrumb.Item>Danh sách Homestay</Breadcrumb.Item>
                        </Breadcrumb>

                        {/* Tiêu đề */}
                        <div style={{ marginBottom: 24 }}>
                            <Title level={2} style={{ marginBottom: 4 }}>
                                Homestay Sóc Sơn - Hà Nội
                            </Title>
                            <Text type="secondary" style={{ fontSize: 15 }}>
                                Tìm thấy {filteredHomestays.length} homestay
                            </Text>
                        </div>

                        {/* Danh sách homestay */}
                        <Row gutter={[16, 16]}>
                            {paginatedHomestays.map((item) => (
                                <Col key={item.id} xs={24} sm={12} lg={12} xl={8}>
                                    <Link to={`/homestay/${item.id}`}>
                                        <Card
                                            hoverable
                                            style={{
                                                borderRadius: 8,
                                                overflow: 'hidden',
                                                height: '100%',
                                            }}
                                            bodyStyle={{ padding: 16 }}
                                            cover={
                                                <div style={{ position: 'relative' }}>
                                                    <img
                                                        alt={item.name}
                                                        src={item.image}
                                                        style={{
                                                            height: 200,
                                                            width: '100%',
                                                            objectFit: 'cover',
                                                        }}
                                                    />
                                                    <Button
                                                        shape="circle"
                                                        icon={<HeartOutlined />}
                                                        size="small"
                                                        style={{
                                                            position: 'absolute',
                                                            top: 12,
                                                            right: 12,
                                                            background: 'white',
                                                        }}
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                        }}
                                                    />
                                                </div>
                                            }
                                        >
                                            <Title level={5} ellipsis style={{ marginBottom: 8, marginTop: 0 }}>
                                                {item.name}
                                            </Title>

                                            <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 12 }}>
                                                <Text type="secondary" style={{ fontSize: 13 }}>
                                                    <EnvironmentOutlined /> {item.location}
                                                </Text>
                                                <Text type="secondary" style={{ fontSize: 13 }}>
                                                    <UserOutlined /> {item.capacity}
                                                </Text>
                                            </Space>

                                            <div style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                borderTop: '1px solid #f0f0f0',
                                                paddingTop: 12,
                                            }}>
                                                <div>
                                                    <Text
                                                        strong
                                                        style={{
                                                            fontSize: 18,
                                                            color: '#1890ff',
                                                        }}
                                                    >
                                                        {item.price.toLocaleString("vi-VN")}đ
                                                    </Text>
                                                    <Text type="secondary" style={{ fontSize: 12 }}> /đêm</Text>
                                                </div>
                                                <Space size={4}>
                                                    <Rate disabled defaultValue={item.rating} style={{ fontSize: 14 }} />
                                                    <Text style={{ fontSize: 13 }}>
                                                        ({item.reviews})
                                                    </Text>
                                                </Space>
                                            </div>
                                        </Card>
                                    </Link>
                                </Col>
                            ))}

                            {filteredHomestays.length === 0 && (
                                <Col span={24}>
                                    <div style={{
                                        textAlign: 'center',
                                        padding: '60px 24px',
                                        background: '#fff',
                                        borderRadius: 8,
                                    }}>
                                        <Title level={4}>
                                            Không tìm thấy homestay phù hợp
                                        </Title>
                                        <Text type="secondary">
                                            Hãy thử điều chỉnh bộ lọc để xem thêm kết quả
                                        </Text>
                                        <br /><br />
                                        <Button
                                            type="primary"
                                            onClick={() => {
                                                setSelectedCategories([]);
                                                setSearchTerm("");
                                                setPriceRange([0, 3000000]);
                                                setCurrentPage(1);
                                            }}
                                        >
                                            Xóa bộ lọc
                                        </Button>
                                    </div>
                                </Col>
                            )}
                        </Row>

                        {/* Phân trang */}
                        {filteredHomestays.length > PAGE_SIZE && (
                            <Row justify="center" style={{ marginTop: 32 }}>
                                <Pagination
                                    current={currentPage}
                                    total={filteredHomestays.length}
                                    pageSize={PAGE_SIZE}
                                    onChange={(page) => {
                                        setCurrentPage(page);
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }}
                                    showSizeChanger={false}
                                />
                            </Row>
                        )}
                    </Col>
                </Row>
            </Layout>

            {/* Drawer bộ lọc cho mobile */}
            <Drawer
                title="Bộ lọc"
                placement="left"
                onClose={() => setDrawerVisible(false)}
                open={drawerVisible}
                width={300}
            >
                <FilterContent />
            </Drawer>

            <AppFooter />

            <style>{`
                @media (max-width: 991px) {
                    .filter-mobile-btn {
                        display: inline-flex !important;
                    }
                }
            `}</style>
        </Layout>
    );
};

export default HomestayListPage;