import React, { useState } from "react";
import { Table, Button, Space, Input, Select, Tag, Modal, Form, message, Card, Row, Col, Statistic, Avatar, Badge } from "antd";
import { PlusOutlined, EditOutlined, DeleteOutlined, EyeOutlined, HomeOutlined, UserOutlined, SearchOutlined, DollarOutlined, CheckCircleOutlined, ClockCircleOutlined, ToolOutlined, CalendarOutlined } from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import { Accommodation } from "../../../types/accommodation/accommodation";
import AddAccommodation from "./addaccommodation";
import EditAccommodation from "./editaccommodation";
import ViewAccommodation from "./viewaccommodation";


// Mẫu dữ liệu mock
const mockData: Accommodation[] = [
    {
        id: 1,
        name: "Homestay Đà Lạt View Núi",
        status: "Trống",
        price: 1200000,
        type: "Phòng đôi",
        manager: "Nguyễn Văn A",
        updatedAt: "2025-10-20",
        address: "123 Đường Trần Phú, Đà Lạt",
        capacity: 2,
        description: "Phòng view núi đẹp, yên tĩnh",
        amenities: ["WiFi", "Điều hòa", "TV"],
    },
    {
        id: 2,
        name: "Villa Biển Nha Trang",
        status: "Đã đặt",
        price: 3500000,
        type: "Villa",
        manager: "Trần Thị B",
        updatedAt: "2025-10-22",
        address: "456 Đường Trần Phú, Nha Trang",
        capacity: 6,
        description: "Villa cao cấp view biển",
        amenities: ["WiFi", "Bể bơi", "BBQ", "Điều hòa"],
    },
    {
        id: 3,
        name: "Căn hộ Hồ Tây",
        status: "Bảo trì",
        price: 1800000,
        type: "Căn hộ",
        manager: "Lê Văn C",
        updatedAt: "2025-10-25",
        address: "789 Đường Âu Cơ, Hà Nội",
        capacity: 4,
        description: "Căn hộ hiện đại, view hồ",
        amenities: ["WiFi", "Điều hòa", "Bếp"],
    },
    {
        id: 4,
        name: "Phòng Studio Quận 1",
        status: "Đang dùng",
        price: 900000,
        type: "Studio",
        manager: "Phạm Thị D",
        updatedAt: "2025-10-26",
        address: "321 Nguyễn Huệ, Q1, TP.HCM",
        capacity: 2,
        description: "Studio tiện nghi trung tâm thành phố",
        amenities: ["WiFi", "Điều hòa"],
    },
    {
        id: 5,
        name: "Homestay Hội An Cổ Kính",
        status: "Trống",
        price: 1500000,
        type: "Phòng gia đình",
        manager: "Hoàng Văn E",
        updatedAt: "2025-10-27",
        address: "567 Phố Cổ, Hội An",
        capacity: 5,
        description: "Không gian truyền thống Hội An",
        amenities: ["WiFi", "Xe đạp", "Tour"],
    },
];

const ListAccommodation: React.FC = () => {
    const [data, setData] = useState<Accommodation[]>(mockData);
    const [filteredData, setFilteredData] = useState<Accommodation[]>(mockData);
    const [searchText, setSearchText] = useState("");
    const [statusFilter, setStatusFilter] = useState("all");
    const [addModal, setAddModal] = useState(false);
    const [editModal, setEditModal] = useState(false);
    const [viewModal, setViewModal] = useState(false);
    const [selectedItem, setSelectedItem] = useState<Accommodation | null>(null);

    // Bộ lọc dữ liệu
    const applyFilters = (search: string, status: string) => {
        let filtered = data;
        if (search) filtered = filtered.filter(item => item.name.toLowerCase().includes(search.toLowerCase()) || item.address?.toLowerCase().includes(search.toLowerCase()));
        if (status !== "all") filtered = filtered.filter(item => item.status === status);
        setFilteredData(filtered);
    };

    const handleSearch = (value: string) => { setSearchText(value); applyFilters(value, statusFilter); };
    const handleStatusChange = (value: string) => { setStatusFilter(value); applyFilters(searchText, value); };

    const handleDelete = (id: number) => {
        Modal.confirm({
            title: "Xác nhận xóa",
            content: "Bạn có chắc chắn muốn xóa phòng này không?",
            okText: "Xóa",
            okType: "danger",
            cancelText: "Hủy",
            onOk: () => {
                const updatedData = data.filter((item) => item.id !== id);
                setData(updatedData);
                applyFilters(searchText, statusFilter);
                message.success("Đã xóa phòng thành công!");
            },
        });
    };

    const columns: ColumnsType<Accommodation> = [
        { title: "Mã", dataIndex: "id", key: "id", width: 70, sorter: (a, b) => a.id - b.id, render: id => `#${id}` },
        { title: "Tên phòng", dataIndex: "name", key: "name", render: (name, record) => <Space><Avatar icon={<HomeOutlined />} />{name}</Space> },
        { title: "Loại", dataIndex: "type", key: "type", render: type => <Tag color="blue">{type}</Tag> },
        { title: "Trạng thái", dataIndex: "status", key: "status", render: status => {
            const config: Record<string, { color: string; icon: any }> = {
                Trống: { color: "success", icon: <CheckCircleOutlined /> },
                "Đã đặt": { color: "processing", icon: <ClockCircleOutlined /> },
                "Đang dùng": { color: "warning", icon: <CalendarOutlined /> },
                "Bảo trì": { color: "error", icon: <ToolOutlined /> },
            };
            return <Tag color={config[status].color} icon={config[status].icon}>{status}</Tag>;
        }},
        { title: "Giá/đêm", dataIndex: "price", key: "price", render: price => `${price.toLocaleString("vi-VN")}₫` },
        { title: "Sức chứa", dataIndex: "capacity", key: "capacity", render: cap => cap || "N/A" },
        { title: "Hành động", key: "action", render: (_, record) => (
            <Space>
                <Button icon={<EyeOutlined />} onClick={() => { setSelectedItem(record); setViewModal(true); }} />
                <Button icon={<EditOutlined />} onClick={() => { setSelectedItem(record); setEditModal(true); }} />
                <Button danger icon={<DeleteOutlined />} onClick={() => handleDelete(record.id)} />
            </Space>
        ) },
    ];

    return (
        <div style={{ padding: 24 }}>
            <Space style={{ marginBottom: 16 }}>
                <Input.Search placeholder="Tìm kiếm tên, địa chỉ..." allowClear onSearch={handleSearch} />
                <Select value={statusFilter} onChange={handleStatusChange}>
                    <Select.Option value="all">Tất cả</Select.Option>
                    <Select.Option value="Trống">Trống</Select.Option>
                    <Select.Option value="Đã đặt">Đã đặt</Select.Option>
                    <Select.Option value="Đang dùng">Đang dùng</Select.Option>
                    <Select.Option value="Bảo trì">Bảo trì</Select.Option>
                </Select>
                <Button type="primary" icon={<PlusOutlined />} onClick={() => setAddModal(true)}>Thêm phòng</Button>
            </Space>
            <Table rowKey="id" columns={columns} dataSource={filteredData} pagination={{ pageSize: 10, showSizeChanger: true, pageSizeOptions: [10,20,50] }} />

            <AddAccommodation
                visible={addModal}
                onCancel={() => setAddModal(false)}
                onAdd={(newData) => { setData([...data, newData]); applyFilters(searchText, statusFilter); }}
            />
            <EditAccommodation
                visible={editModal}
                accommodation={selectedItem}
                onCancel={() => setEditModal(false)}
                onUpdate={(updated) => { setData(data.map(d => d.id === updated.id ? updated : d)); applyFilters(searchText, statusFilter); }}
            />
            <ViewAccommodation
                visible={viewModal}
                accommodation={selectedItem}
                onCancel={() => setViewModal(false)}
            />
        </div>
    );
};

export default ListAccommodation;
