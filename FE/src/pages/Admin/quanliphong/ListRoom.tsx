import React, { useState } from "react";
import {
  Table,
  Card,
  Space,
  Input,
  Button,
  Switch,
  message,
  Modal,
  Tooltip,
  Image,
} from "antd";
import {
  SearchOutlined,
  PlusOutlined,
  EditOutlined,
  DeleteOutlined,
  HomeOutlined,
} from "@ant-design/icons";
import type { ColumnsType, TablePaginationConfig } from "antd/es/table";

import { Listing } from "../../../types/room/room";
import { deleteListing, getListings, updateListing } from "../../../service/room";
import AddRoom from "./addroom";
import EditRoom from "./editroom";

const ListRoom: React.FC = () => {
  // State quản lý danh sách phòng
  const [roomList, setRoomList] = useState<Listing[]>([
    {
      key: "1",
      id: 1,
      name: "Villa Biển Xanh",
      location: "Nha Trang",
      price: 2500000,
      rating: 4.8,
      status: "available",
      image: "https://images.unsplash.com/photo-1505691723518-36a5ac3be353",
      createdAt: "2023-02-01",
      updatedAt: "2024-09-20",
      verified: true,
    },
    {
      key: "2",
      id: 2,
      name: "Homestay Gió Biển",
      location: "Phú Quốc",
      price: 1500000,
      rating: 4.3,
      status: "available",
      image: "https://images.unsplash.com/photo-1512917774080-9991f1c4c750",
      createdAt: "2023-03-10",
      updatedAt: "2024-10-05",
      verified: false,
    },
    {
      key: "3",
      id: 3,
      name: "Nhà Gỗ Tây Bắc",
      location: "Sapa",
      price: 800000,
      rating: 4.5,
      status: "unavailable",
      image: "https://images.unsplash.com/photo-1518780664697-55e3ad937233",
      createdAt: "2023-04-15",
      updatedAt: "2024-09-25",
      verified: true,
    },
  ]);

  const [searchText, setSearchText] = useState("");
  const [pageSize, setPageSize] = useState<number>(15);
  const [selectedListing, setSelectedListing] = useState<Listing | null>(null);
  const [modalMode, setModalMode] = useState<"add" | "edit">("add");
  const [isModalVisible, setIsModalVisible] = useState(false);

  // Thay đổi trạng thái còn/hết phòng
  const handleStatusChange = (checked: boolean, record: Listing) => {
    Modal.confirm({
      title: "Xác nhận thay đổi trạng thái",
      content: `Bạn có chắc muốn ${checked ? "mở" : "đóng"} phòng "${record.name}"?`,
      okText: "Xác nhận",
      cancelText: "Hủy",
      onOk: () => {
        updateListing({ ...record, status: checked ? "available" : "unavailable" });
        setRoomList([...getListings()]);
        message.success("Cập nhật trạng thái thành công!");
      },
    });
  };

  // Lọc danh sách theo tìm kiếm
  const filteredList = roomList.filter(
    (l) =>
      l.name.toLowerCase().includes(searchText.toLowerCase()) ||
      l.location.toLowerCase().includes(searchText.toLowerCase())
  );

  // Cấu hình phân trang
  const pagination: TablePaginationConfig = {
    pageSize,
    showSizeChanger: true,
    pageSizeOptions: ["15", "30", "45"],
    onShowSizeChange: (_, size) => setPageSize(size),
    showTotal: (total) => `Tổng ${total} phòng`,
  };

  // Cột Table
  const columns: ColumnsType<Listing> = [
    { title: "ID", dataIndex: "id", key: "id", render: (id) => <>#{id}</> },
    {
      title: "Hình ảnh",
      dataIndex: "image",
      key: "image",
      render: (image) => (
        <Image
          src={image}
          alt="Listing"
          width={70}
          height={70}
          style={{ borderRadius: 8 }}
          placeholder
        />
      ),
    },
    { title: "Tên phòng", dataIndex: "name", key: "name" },
    { title: "Địa điểm", dataIndex: "location", key: "location" },
    {
      title: "Giá (VNĐ)",
      dataIndex: "price",
      key: "price",
      render: (price) => price.toLocaleString(),
    },
    { title: "Đánh giá", dataIndex: "rating", key: "rating" },
    {
      title: "Trạng thái",
      dataIndex: "status",
      key: "status",
      render: (_, record) => (
        <Switch
          checked={record.status === "available"}
          onChange={(checked) => handleStatusChange(checked, record)}
          checkedChildren="Còn"
          unCheckedChildren="Hết"
        />
      ),
    },
    {
      title: "Thao tác",
      key: "actions",
      render: (_, record) => (
        <Space>
          <Tooltip title="Chỉnh sửa">
            <Button
              onClick={() => {
                setSelectedListing(record);
                setModalMode("edit");
                setIsModalVisible(true);
              }}
              icon={<EditOutlined />}
            />
          </Tooltip>
          <Tooltip title="Xóa">
            <Button
              danger
              onClick={() => {
                Modal.confirm({
                  title: "Xóa phòng",
                  content: `Bạn có chắc muốn xóa "${record.name}"?`,
                  onOk: () => {
                    deleteListing(record.key);
                    setRoomList([...getListings()]);
                    message.success("Đã xóa phòng!");
                  },
                });
              }}
              icon={<DeleteOutlined />}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <Card
      title={
        <Space>
          <HomeOutlined /> Danh sách phòng
        </Space>
      }
      extra={
        <Space>
          <Input
            placeholder="Tìm kiếm..."
            prefix={<SearchOutlined />}
            onChange={(e) => setSearchText(e.target.value)}
          />
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => {
              setModalMode("add");
              setIsModalVisible(true);
            }}
          >
            Thêm phòng
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={filteredList}
        pagination={pagination}
        rowKey="key"
        scroll={{ x: 1000 }}
      />

      {/* Modal thêm / sửa */}
      {modalMode === "add" && (
        <AddRoom
          visible={isModalVisible}
          onClose={() => {
            setIsModalVisible(false);
            setRoomList([...getListings()]);
          }}
        />
      )}
      {modalMode === "edit" && selectedListing && (
        <EditRoom
          visible={isModalVisible}
          listing={selectedListing}
          onClose={() => {
            setIsModalVisible(false);
            setRoomList([...getListings()]);
          }}
        />
      )}
    </Card>
  );
};

export default ListRoom;
