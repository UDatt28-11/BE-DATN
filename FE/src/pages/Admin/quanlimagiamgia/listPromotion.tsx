// src/pages/quanlimagiamgia/listPromotion.tsx
import React, { useState } from "react";
import {
    Table, Button, Space, Input, Select, Tag, message,
    Card, Row, Col, Statistic, Tooltip, Badge, Popconfirm
} from "antd";
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    PercentageOutlined, CheckCircleOutlined, CloseCircleOutlined,
    TagsOutlined, GiftOutlined, EnvironmentOutlined, DollarOutlined, CopyOutlined
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { useNavigate } from "react-router-dom";
import { Promotion, PromotionStatus } from "../../../types/promotion/promotion";


const { Search } = Input;
const { Option } = Select;

// const mockPromotions: Promotion[] = [/* ... (giữ nguyên mock dữ liệu ban đầu) ... */];

const mockPromotions: Promotion[] = [
    {
        id: "1",
        code: "SUMMER2025",
        name: "Ưu đãi mùa hè 2025",
        description: "Giảm 20% cho tất cả đặt phòng trong mùa hè",
        discountType: "Phần trăm",
        discountValue: 20,
        minOrderValue: 1000000,
        maxDiscount: 500000,
        startDate: "2025-06-01",
        endDate: "2025-08-31",
        usageLimit: 1000,
        usedCount: 245,
        status: "Chưa áp dụng",
        applicableLocations: ["Đà Lạt", "Nha Trang", "Phú Quốc"],
        createdAt: "2025-05-15",
        updatedAt: "2025-10-20",
    },
    {
        id: "2",
        code: "DALAT50K",
        name: "Giảm 50K Đà Lạt",
        description: "Giảm 50.000đ cho homestay tại Đà Lạt",
        discountType: "Số tiền cố định",
        discountValue: 50000,
        minOrderValue: 500000,
        startDate: "2025-01-01",
        endDate: "2025-12-31",
        usageLimit: 500,
        usedCount: 156,
        status: "Đang hoạt động",
        applicableLocations: ["Đà Lạt"],
        createdAt: "2025-01-01",
        updatedAt: "2025-10-22",
    },
    {
        id: "3",
        code: "NEWYEAR30",
        name: "Tết Nguyên Đán 2025",
        description: "Giảm 30% dịp Tết Nguyên Đán",
        discountType: "Phần trăm",
        discountValue: 30,
        minOrderValue: 2000000,
        maxDiscount: 1000000,
        startDate: "2025-01-25",
        endDate: "2025-02-05",
        usageLimit: 200,
        usedCount: 200,
        status: "Hết hạn",
        applicableLocations: ["Tất cả"],
        createdAt: "2024-12-20",
        updatedAt: "2025-02-05",
    },
    {
        id: "4",
        code: "WELCOME100K",
        name: "Chào mừng khách mới",
        description: "Giảm 100.000đ cho khách hàng đặt phòng lần đầu",
        discountType: "Số tiền cố định",
        discountValue: 100000,
        minOrderValue: 800000,
        startDate: "2025-10-01",
        endDate: "2025-12-31",
        usageLimit: 2000,
        usedCount: 567,
        status: "Đang hoạt động",
        applicableLocations: ["Tất cả"],
        createdAt: "2025-10-01",
        updatedAt: "2025-10-28",
    },
    {
        id: "5",
        code: "WEEKEND15",
        name: "Giảm giá cuối tuần",
        description: "Giảm 15% cho đặt phòng cuối tuần",
        discountType: "Phần trăm",
        discountValue: 15,
        minOrderValue: 500000,
        maxDiscount: 300000,
        startDate: "2025-10-01",
        endDate: "2025-11-30",
        usageLimit: 1500,
        usedCount: 89,
        status: "Đang hoạt động",
        applicableLocations: ["Hà Nội", "TP.HCM", "Hội An"],
        createdAt: "2025-10-01",
        updatedAt: "2025-10-25",
    },
];

const ListPromotion: React.FC = () => {
    const navigate = useNavigate();
    const [promotions, setPromotions] = useState<Promotion[]>(mockPromotions);
    const [filteredPromotions, setFilteredPromotions] = useState<Promotion[]>(mockPromotions);
    const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
    const [searchText, setSearchText] = useState("");
    const [statusFilter, setStatusFilter] = useState<string>("all");

    // --- Bộ lọc ---
    const applyFilters = (search: string, status: string) => {
        let filtered = promotions;
        if (search) {
            const searchLower = search.toLowerCase();
            filtered = filtered.filter(
                (p) =>
                    p.code.toLowerCase().includes(searchLower) ||
                    p.name.toLowerCase().includes(searchLower) ||
                    p.description.toLowerCase().includes(searchLower)
            );
        }
        if (status !== "all") filtered = filtered.filter((p) => p.status === status);
        setFilteredPromotions(filtered);
    };

    const handleSearch = (value: string) => {
        setSearchText(value);
        applyFilters(value, statusFilter);
    };

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);
        applyFilters(searchText, value);
    };

    // --- Xử lý trạng thái & xóa ---
    const handleStatusChange = (id: string, newStatus: PromotionStatus) => {
        const updated = promotions.map((p) => (p.id === id ? { ...p, status: newStatus } : p));
        setPromotions(updated);
        applyFilters(searchText, statusFilter);
        message.success("Đã cập nhật trạng thái!");
    };

    const handleDelete = (id: string) => {
        const updated = promotions.filter((p) => p.id !== id);
        setPromotions(updated);
        applyFilters(searchText, statusFilter);
        message.success("Đã xóa mã giảm giá!");
    };

    const handleBulkDelete = () => {
        const updated = promotions.filter((p) => !selectedRowKeys.includes(p.id));
        setPromotions(updated);
        applyFilters(searchText, statusFilter);
        setSelectedRowKeys([]);
        message.success(`Đã xóa ${selectedRowKeys.length} mã giảm giá!`);
    };

    const handleCopyCode = (code: string) => {
        navigator.clipboard.writeText(code);
        message.success("Đã sao chép mã!");
    };

    const columns: ColumnsType<Promotion> = [
        {
            title: "Mã",
            dataIndex: "code",
            key: "code",
            sorter: (a, b) => a.code.localeCompare(b.code),
            render: (code) => (
                <Space>
                    <span style={{ fontWeight: 600, color: "#1890ff" }}>{code}</span>
                    <Tooltip title="Sao chép">
                        <Button type="text" size="small" icon={<CopyOutlined />} onClick={() => handleCopyCode(code)} />
                    </Tooltip>
                </Space>
            ),
        },
        {
            title: "Tên chương trình",
            dataIndex: "name",
            key: "name",
            sorter: (a, b) => a.name.localeCompare(b.name),
        },
        {
            title: "Loại giảm",
            dataIndex: "discountType",
            key: "discountType",
            render: (type, record) => (
                <Tag color={type === "Phần trăm" ? "blue" : "green"}>
                    {type === "Phần trăm" ? `${record.discountValue}%` : `${record.discountValue.toLocaleString("vi-VN")}₫`}
                </Tag>
            ),
        },
        {
            title: "Điều kiện",
            render: (_, record) => (
                <div>
                    <DollarOutlined style={{ color: "#f5222d", marginRight: 4 }} />
                    Tối thiểu: {record.minOrderValue.toLocaleString("vi-VN")}₫
                    {record.maxDiscount && (
                        <div style={{ fontSize: 12 }}>
                            <PercentageOutlined /> Tối đa: {record.maxDiscount.toLocaleString("vi-VN")}₫
                        </div>
                    )}
                </div>
            ),
        },
        {
            title: "Trạng thái",
            dataIndex: "status",
            key: "status",
            render: (status, record) => (
                <Select
                    value={status}
                    onChange={(value) => handleStatusChange(record.id, value)}
                    style={{ width: 140 }}
                >
                    <Option value="Đang hoạt động">Đang hoạt động</Option>
                    <Option value="Chưa áp dụng">Chưa áp dụng</Option>
                    <Option value="Hết hạn">Hết hạn</Option>
                    <Option value="Vô hiệu hóa">Vô hiệu hóa</Option>
                </Select>
            ),
        },
        {
            title: "Hành động",
            key: "action",
            render: (_, record) => (
                <Space>
                    <Tooltip title="Sửa">
                        <Button
                            icon={<EditOutlined />}
                            onClick={() => navigate(`/quanlimagiamgia/edit/${record.id}`)}
                        />
                    </Tooltip>
                    <Popconfirm
                        title="Xóa mã này?"
                        onConfirm={() => handleDelete(record.id)}
                        okText="Xóa"
                        cancelText="Hủy"
                    >
                        <Button danger icon={<DeleteOutlined />} />
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    const rowSelection = {
        selectedRowKeys,
        onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
    };

    return (
        <div style={{ padding: 24 }}>
            <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
                <Space>
                    <Search
                        placeholder="Tìm kiếm mã hoặc tên..."
                        enterButton={<SearchOutlined />}
                        onSearch={handleSearch}
                        onChange={(e) => handleSearch(e.target.value)}
                        style={{ width: 300 }}
                    />
                    <Select
                        value={statusFilter}
                        onChange={handleStatusFilterChange}
                        style={{ width: 160 }}
                    >
                        <Option value="all">Tất cả</Option>
                        <Option value="Đang hoạt động">Đang hoạt động</Option>
                        <Option value="Chưa áp dụng">Chưa áp dụng</Option>
                        <Option value="Hết hạn">Hết hạn</Option>
                        <Option value="Vô hiệu hóa">Vô hiệu hóa</Option>
                    </Select>
                </Space>
                <Space>
                    {selectedRowKeys.length > 0 && (
                        <Popconfirm
                            title={`Xóa ${selectedRowKeys.length} mã?`}
                            onConfirm={handleBulkDelete}
                        >
                            <Button danger icon={<DeleteOutlined />}>
                                Xóa ({selectedRowKeys.length})
                            </Button>
                        </Popconfirm>
                    )}
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => navigate("/quanlimagiamgia/add")}
                    >
                        Thêm mã
                    </Button>
                </Space>
            </Row>

            <Table
                rowSelection={rowSelection}
                columns={columns}
                dataSource={filteredPromotions}
                rowKey="id"
                pagination={{
                    pageSize: 15,
                    showSizeChanger: true,
                    pageSizeOptions: [15, 30, 45],
                }}
            />
        </div>
    );
};

export default ListPromotion;
