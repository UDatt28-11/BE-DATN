import React, { useState } from "react";
import {
  Table,
  Input,
  Button,
  Space,
  Tag,
  Modal,
  Dropdown,
  Select,
} from "antd";
import {
  PlusOutlined,
  EditOutlined,
  DeleteOutlined,
  SettingOutlined,
  MoreOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import { Amenity } from "../../../types/amenity/amenity";
import AddAmenity from "./addamenity";
import EditAmenity from "./editamenity";
import VariantAmenity from "./variantamenity";

const { Search } = Input;

const initialData: Amenity[] = [
  {
    id: 1,
    name: "Wi-Fi miễn phí",
    type: "Cơ bản",
    icon: "📶",
    description: "Kết nối Internet tốc độ cao",
    status: "Hoạt động",
    createdAt: "2025-10-01",
    updatedAt: "2025-10-10",
  },
  {
    id: 2,
    name: "Bếp riêng",
    type: "Cơ bản",
    icon: "🍳",
    description: "Đầy đủ dụng cụ nấu ăn",
    status: "Hoạt động",
    createdAt: "2025-09-15",
    updatedAt: "2025-09-30",
  },
  {
    id: 3,
    name: "Hồ bơi ngoài trời",
    type: "Nâng cao",
    icon: "🏊",
    description: "Hồ bơi rộng 25m",
    status: "Ẩn",
    createdAt: "2025-08-20",
    updatedAt: "2025-09-05",
  },
];

const ListAmenity: React.FC = () => {
  const [data, setData] = useState<Amenity[]>(initialData);
  const [search, setSearch] = useState("");
  const [filterType, setFilterType] = useState<string | null>(null);
  const [addModal, setAddModal] = useState(false);
  const [editModal, setEditModal] = useState(false);
  const [variantModal, setVariantModal] = useState(false);
  const [selectedAmenity, setSelectedAmenity] = useState<Amenity | null>(null);

  // Filter và search
  const filteredData = data.filter(
    (item) =>
      (item.name.toLowerCase().includes(search.toLowerCase()) ||
        item.description.toLowerCase().includes(search.toLowerCase())) &&
      (filterType ? item.type === filterType : true)
  );

  const handleDelete = (id: number) => {
    Modal.confirm({
      title: "Xóa tiện ích này?",
      content: "Thao tác này không thể hoàn tác.",
      okText: "Xóa",
      okType: "danger",
      cancelText: "Hủy",
      onOk: () => setData(data.filter((item) => item.id !== id)),
    });
  };

  const columns: ColumnsType<Amenity> = [
    {
      title: "ID",
      dataIndex: "id",
      sorter: (a, b) => a.id - b.id,
      width: 70,
    },
    {
      title: "Tên tiện ích",
      dataIndex: "name",
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: "Biểu tượng",
      dataIndex: "icon",
      width: 100,
      render: (icon: Amenity["icon"]) => <span style={{ fontSize: 20 }}>{icon}</span>,
    },
    {
      title: "Loại",
      dataIndex: "type",
      filters: [
        { text: "Cơ bản", value: "Cơ bản" },
        { text: "Nâng cao", value: "Nâng cao" },
      ],
      onFilter: (value, record) => record.type === value,
      render: (type: Amenity["type"]) => (
        <Tag color={type === "Cơ bản" ? "blue" : "purple"}>{type}</Tag>
      ),
    },
    {
      title: "Trạng thái",
      dataIndex: "status",
      render: (status: Amenity["status"]) => (
        <Tag color={status === "Hoạt động" ? "green" : "red"}>{status}</Tag>
      ),
    },
    {
      title: "Ngày cập nhật",
      dataIndex: "updatedAt",
      sorter: (a, b) => new Date(a.updatedAt).getTime() - new Date(b.updatedAt).getTime(),
    },
    {
      title: "Thao tác",
      key: "actions",
      render: (_: any, record: Amenity) => {
        const menuItems = [
          {
            key: "edit",
            label: "Chỉnh sửa",
            icon: <EditOutlined />,
            onClick: () => {
              setSelectedAmenity(record);
              setEditModal(true);
            },
          },
          {
            key: "variant",
            label: "Giá trị tiện ích",
            icon: <SettingOutlined />,
            onClick: () => {
              setSelectedAmenity(record);
              setVariantModal(true);
            },
          },
          {
            key: "delete",
            label: "Xóa",
            icon: <DeleteOutlined />,
            danger: true,
            onClick: () => handleDelete(record.id),
          },
        ];
        return (
          <Dropdown menu={{ items: menuItems }} trigger={["click"]}>
            <Button icon={<MoreOutlined />} />
          </Dropdown>
        );
      },
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16, flexWrap: "wrap" }}>
        <Search
          placeholder="Tìm kiếm tiện ích..."
          allowClear
          onSearch={setSearch}
          style={{ width: 260 }}
        />
        <Select
          placeholder="Lọc theo loại"
          allowClear
          style={{ width: 160 }}
          onChange={setFilterType}
        >
          <Select.Option value="Cơ bản">Cơ bản</Select.Option>
          <Select.Option value="Nâng cao">Nâng cao</Select.Option>
        </Select>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => setAddModal(true)}
        >
          Thêm tiện ích
        </Button>
      </Space>

      <Table
        rowKey="id"
        columns={columns}
        dataSource={filteredData}
        pagination={{ pageSize: 15, showSizeChanger: true, pageSizeOptions: [15, 30, 45] }}
        bordered
      />

      <AddAmenity
        visible={addModal}
        onCancel={() => setAddModal(false)}
        onAdd={(newData) => setData([...data, newData])}
      />
      <EditAmenity
        visible={editModal}
        amenity={selectedAmenity}
        onCancel={() => setEditModal(false)}
        onUpdate={(updated) =>
          setData(data.map((d) => (d.id === updated.id ? updated : d)))
        }
      />
      <VariantAmenity
        visible={variantModal}
        amenity={selectedAmenity}
        onCancel={() => setVariantModal(false)}
      />
    </div>
  );
};

export default ListAmenity;
