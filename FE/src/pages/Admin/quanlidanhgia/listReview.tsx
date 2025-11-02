import React, { useState } from "react";
import {
  Table,
  Space,
  Button,
  Tag,
  Tooltip,
  Rate,
  Image,
  Modal,
  Select,
  Input,
  message,
} from "antd";
import {
  EyeOutlined,
  DeleteOutlined,
  SearchOutlined,
  VideoCameraOutlined,
  StarOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";

import dayjs from "dayjs";
import { Review } from "../../../types/review/review";

const { Option } = Select;
const { Search } = Input;

// 🔹 Mock dữ liệu mẫu
const mockReviews: Review[] = [
  {
    id: "1",
    userName: "Nguyễn Văn A",
    userAvatar: "https://i.pravatar.cc/100?img=12",
    accommodationName: "Resort Biển Xanh",
    roomName: "Phòng Deluxe Sea View",
    rating: 5,
    comment: "Dịch vụ tuyệt vời, nhân viên thân thiện, view biển rất đẹp!",
    media: [
      { id: "m1", type: "image", url: "https://placekitten.com/200/140" },
      { id: "m2", type: "video", url: "https://www.w3schools.com/html/mov_bbb.mp4" },
    ],
    createdAt: "2025-08-15T10:00:00",
    status: "Hiển thị",
  },
  {
    id: "2",
    userName: "Trần Thị B",
    accommodationName: "Khách sạn Hoa Mai",
    rating: 3,
    comment: "Phòng ổn, nhưng hơi ồn ào, ăn sáng chưa đa dạng.",
    media: [],
    createdAt: "2025-08-20T15:30:00",
    status: "Hiển thị",
  },
  {
    id: "3",
    userName: "Phạm Quốc C",
    accommodationName: "Villa Núi Rừng",
    rating: 4,
    comment: "Không gian yên tĩnh, phù hợp nghỉ dưỡng. Sẽ quay lại.",
    media: [{ id: "m3", type: "image", url: "https://placekitten.com/300/200" }],
    createdAt: "2025-09-01T12:45:00",
    status: "Ẩn",
  },
];

const ListReview: React.FC = () => {
  const [reviews, setReviews] = useState<Review[]>(mockReviews);
  const [filtered, setFiltered] = useState<Review[]>(mockReviews);
  const [searchText, setSearchText] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [modalReview, setModalReview] = useState<Review | null>(null);

  // --- Bộ lọc ---
  const applyFilters = (text: string, status: string) => {
    let data = reviews;
    if (text) {
      const lower = text.toLowerCase();
      data = data.filter(
        (r) =>
          r.userName.toLowerCase().includes(lower) ||
          r.accommodationName.toLowerCase().includes(lower) ||
          r.comment.toLowerCase().includes(lower)
      );
    }
    if (status !== "all") data = data.filter((r) => r.status === status);
    setFiltered(data);
  };

  const handleSearch = (value: string) => {
    setSearchText(value);
    applyFilters(value, statusFilter);
  };

  const handleStatusChange = (id: string, newStatus: "Hiển thị" | "Ẩn") => {
    const updated = reviews.map((r) =>
      r.id === id ? { ...r, status: newStatus } : r
    );
    setReviews(updated);
    applyFilters(searchText, statusFilter);
    message.success("Đã cập nhật trạng thái đánh giá!");
  };

  const handleDelete = (id: string) => {
    Modal.confirm({
      title: "Xóa đánh giá này?",
      onOk: () => {
        const updated = reviews.filter((r) => r.id !== id);
        setReviews(updated);
        applyFilters(searchText, statusFilter);
        message.success("Đã xóa đánh giá!");
      },
    });
  };

  const columns: ColumnsType<Review> = [
    {
      title: "Người đánh giá",
      dataIndex: "userName",
      key: "userName",
      render: (name, record) => (
        <Space>
          {record.userAvatar ? (
            <img
              src={record.userAvatar}
              alt={name}
              style={{ width: 32, height: 32, borderRadius: "50%" }}
            />
          ) : (
            <StarOutlined style={{ fontSize: 20, color: "#faad14" }} />
          )}
          <span>{name}</span>
        </Space>
      ),
    },
    {
      title: "Cơ sở lưu trú",
      dataIndex: "accommodationName",
      key: "accommodationName",
    },
    {
      title: "Sao",
      dataIndex: "rating",
      key: "rating",
      render: (rating) => <Rate disabled defaultValue={rating} />,
      sorter: (a, b) => a.rating - b.rating,
    },
    {
      title: "Nhận xét",
      dataIndex: "comment",
      key: "comment",
      ellipsis: true,
    },
    {
      title: "Ảnh/Video",
      key: "media",
      render: (_, record) =>
        record.media.length > 0 ? (
          <Button
            icon={<EyeOutlined />}
            size="small"
            onClick={() => setModalReview(record)}
          >
            Xem ({record.media.length})
          </Button>
        ) : (
          <Tag color="default">Không có</Tag>
        ),
    },
    {
      title: "Ngày tạo",
      dataIndex: "createdAt",
      render: (date) => dayjs(date).format("DD/MM/YYYY HH:mm"),
      sorter: (a, b) => dayjs(a.createdAt).unix() - dayjs(b.createdAt).unix(),
    },
    {
      title: "Trạng thái",
      dataIndex: "status",
      key: "status",
      render: (status, record) => (
        <Select
          value={status}
          onChange={(value) =>
            handleStatusChange(record.id, value as "Hiển thị" | "Ẩn")
          }
          style={{ width: 120 }}
        >
          <Option value="Hiển thị">Hiển thị</Option>
          <Option value="Ẩn">Ẩn</Option>
        </Select>
      ),
    },
    {
      title: "Hành động",
      key: "action",
      render: (_, record) => (
        <Space>
          <Tooltip title="Xem chi tiết">
            <Button
              icon={<EyeOutlined />}
              onClick={() => setModalReview(record)}
            />
          </Tooltip>
          <Tooltip title="Xóa">
            <Button danger icon={<DeleteOutlined />} onClick={() => handleDelete(record.id)} />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Search
          placeholder="Tìm người dùng, cơ sở, nhận xét..."
          onSearch={handleSearch}
          onChange={(e) => handleSearch(e.target.value)}
          allowClear
          enterButton={<SearchOutlined />}
          style={{ width: 300 }}
        />
        <Select
          value={statusFilter}
          onChange={(value) => {
            setStatusFilter(value);
            applyFilters(searchText, value);
          }}
          style={{ width: 160 }}
        >
          <Option value="all">Tất cả</Option>
          <Option value="Hiển thị">Hiển thị</Option>
          <Option value="Ẩn">Ẩn</Option>
        </Select>
      </Space>

      <Table
        columns={columns}
        dataSource={filtered}
        rowKey="id"
        pagination={{ pageSize: 10 }}
      />

      {/* Modal xem chi tiết */}
      <Modal
        open={!!modalReview}
        onCancel={() => setModalReview(null)}
        footer={null}
        title="Chi tiết đánh giá"
        width={700}
      >
        {modalReview && (
          <div>
            <h3 style={{ marginBottom: 8 }}>
              {modalReview.userName} –{" "}
              <Rate disabled defaultValue={modalReview.rating} />
            </h3>
            <p>
              <strong>Chỗ ở:</strong> {modalReview.accommodationName}
            </p>
            <p>
              <strong>Nhận xét:</strong> {modalReview.comment}
            </p>

            {modalReview.media.length > 0 && (
              <>
                <h4>Ảnh/Video:</h4>
                <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
                  {modalReview.media.map((m) =>
                    m.type === "image" ? (
                      <Image
                        key={m.id}
                        width={120}
                        height={90}
                        src={m.url}
                        style={{ borderRadius: 6 }}
                      />
                    ) : (
                      <video
                        key={m.id}
                        width={200}
                        controls
                        style={{ borderRadius: 6 }}
                      >
                        <source src={m.url} type="video/mp4" />
                      </video>
                    )
                  )}
                </div>
              </>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
};

export default ListReview;
