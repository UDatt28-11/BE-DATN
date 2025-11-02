import React, { useState } from "react";
import {
  Table,
  Space,
  Button,
  Modal,
  Input,
  Select,
  Tag,
  Avatar,
  message as toast,
} from "antd";
import {
  MessageOutlined,
  DeleteOutlined,
  EyeOutlined,
  SendOutlined,
  UserOutlined,
  CommentOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { Message, MessageReply } from "../../../types/message/message";


const { TextArea } = Input;
const { Option } = Select;

// 🔹 Dữ liệu mẫu
const mockMessages: Message[] = [
  {
    id: "1",
    userName: "Nguyễn Văn A",
    userAvatar: "https://i.pravatar.cc/100?img=10",
    accommodationName: "Villa Biển Xanh",
    content: "Phòng đẹp nhưng nhân viên phục vụ hơi chậm.",
    createdAt: "2025-10-10T09:00:00",
    replies: [
      {
        id: "r1",
        sender: "admin",
        content: "Cảm ơn bạn đã góp ý, chúng tôi sẽ cải thiện dịch vụ.",
        createdAt: "2025-10-11T08:30:00",
      },
    ],
    status: "Hiển thị",
  },
  {
    id: "2",
    userName: "Trần Thị B",
    accommodationName: "Resort Hoa Hồng",
    content: "View đẹp, sạch sẽ, nhân viên thân thiện!",
    createdAt: "2025-10-09T15:00:00",
    replies: [],
    status: "Hiển thị",
  },
  {
    id: "3",
    userName: "Phạm Quốc C",
    accommodationName: "Khách sạn Mặt Trăng",
    content: "Phòng không giống hình, khá thất vọng.",
    createdAt: "2025-10-08T21:00:00",
    replies: [],
    status: "Ẩn",
  },
];

const ListMessage: React.FC = () => {
  const [messages, setMessages] = useState<Message[]>(mockMessages);
  const [filtered, setFiltered] = useState<Message[]>(mockMessages);
  const [statusFilter, setStatusFilter] = useState("all");
  const [selectedMsg, setSelectedMsg] = useState<Message | null>(null);
  const [replyContent, setReplyContent] = useState("");

  // --- Bộ lọc theo trạng thái ---
  const applyFilter = (status: string) => {
    if (status === "all") setFiltered(messages);
    else setFiltered(messages.filter((m) => m.status === status));
  };

  const handleStatusChange = (id: string, newStatus: "Hiển thị" | "Ẩn") => {
    const updated = messages.map((m) =>
      m.id === id ? { ...m, status: newStatus } : m
    );
    setMessages(updated);
    applyFilter(statusFilter);
    toast.success("Đã cập nhật trạng thái bình luận!");
  };

  const handleDelete = (id: string) => {
    Modal.confirm({
      title: "Xóa bình luận này?",
      onOk: () => {
        const updated = messages.filter((m) => m.id !== id);
        setMessages(updated);
        applyFilter(statusFilter);
        toast.success("Đã xóa bình luận!");
      },
    });
  };

  const handleReply = () => {
  if (!replyContent.trim() || !selectedMsg) return;

  const reply: MessageReply = {
    id: Date.now().toString(),
    sender: "admin",
    content: replyContent,
    createdAt: new Date().toISOString(),
  };

  const updated = messages.map((m) =>
    m.id === selectedMsg.id
      ? { ...m, replies: [...m.replies, reply] }
      : m
  );

  setMessages(updated);
  setSelectedMsg({
    ...selectedMsg,
    replies: [...selectedMsg.replies, reply],
  });
  setReplyContent("");
  toast.success("Đã gửi phản hồi!");
};


  const columns: ColumnsType<Message> = [
    {
      title: "Người bình luận",
      dataIndex: "userName",
      key: "userName",
      render: (name, record) => (
        <Space>
          <Avatar src={record.userAvatar} icon={<UserOutlined />} />
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
      title: "Nội dung",
      dataIndex: "content",
      key: "content",
      ellipsis: true,
    },
    {
      title: "Ngày gửi",
      dataIndex: "createdAt",
      key: "createdAt",
      render: (date) => dayjs(date).format("DD/MM/YYYY HH:mm"),
    },
    {
      title: "Trạng thái",
      dataIndex: "status",
      render: (status, record) => (
        <Select
          value={status}
          onChange={(v) => handleStatusChange(record.id, v as "Hiển thị" | "Ẩn")}
          style={{ width: 120 }}
        >
          <Option value="Hiển thị">Hiển thị</Option>
          <Option value="Ẩn">Ẩn</Option>
        </Select>
      ),
    },
    {
      title: "Hành động",
      key: "actions",
      render: (_, record) => (
        <Space>
          <Button
            icon={<EyeOutlined />}
            onClick={() => setSelectedMsg(record)}
          >
            Xem
          </Button>
          <Button
            danger
            icon={<DeleteOutlined />}
            onClick={() => handleDelete(record.id)}
          />
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2 style={{ marginBottom: 16 }}>
        <CommentOutlined /> Quản lý bình luận
      </h2>

      <Space style={{ marginBottom: 16 }}>
        <Select
          value={statusFilter}
          onChange={(v) => {
            setStatusFilter(v);
            applyFilter(v);
          }}
          style={{ width: 160 }}
        >
          <Option value="all">Tất cả</Option>
          <Option value="Hiển thị">Hiển thị</Option>
          <Option value="Ẩn">Ẩn</Option>
        </Select>
      </Space>

      <Table
        rowKey="id"
        dataSource={filtered}
        columns={columns}
        pagination={{ pageSize: 10 }}
      />

      {/* 🔹 Modal xem & phản hồi */}
      <Modal
        open={!!selectedMsg}
        onCancel={() => setSelectedMsg(null)}
        title="Chi tiết bình luận"
        footer={null}
        width={700}
      >
        {selectedMsg && (
          <div>
            <div style={{ marginBottom: 12 }}>
              <Space>
                <Avatar
                  src={selectedMsg.userAvatar}
                  icon={<UserOutlined />}
                  size={40}
                />
                <div>
                  <strong>{selectedMsg.userName}</strong>
                  <div style={{ color: "#888" }}>
                    {dayjs(selectedMsg.createdAt).format("DD/MM/YYYY HH:mm")}
                  </div>
                </div>
              </Space>
            </div>

            <p style={{ margin: "10px 0", fontSize: 16 }}>
              💬 {selectedMsg.content}
            </p>

            <div
              style={{
                background: "#fafafa",
                padding: 12,
                borderRadius: 6,
                marginTop: 12,
              }}
            >
              <h4>Phản hồi:</h4>
              {selectedMsg.replies.length === 0 ? (
                <Tag color="default">Chưa có phản hồi</Tag>
              ) : (
                selectedMsg.replies.map((r) => (
                  <div
                    key={r.id}
                    style={{
                      background:
                        r.sender === "admin" ? "#e6f7ff" : "white",
                      padding: 8,
                      borderRadius: 6,
                      marginBottom: 6,
                    }}
                  >
                    <strong>
                      {r.sender === "admin" ? "Admin" : selectedMsg.userName}:
                    </strong>{" "}
                    {r.content}
                    <div style={{ fontSize: 12, color: "#888" }}>
                      {dayjs(r.createdAt).format("DD/MM/YYYY HH:mm")}
                    </div>
                  </div>
                ))
              )}
            </div>

            <div style={{ marginTop: 16 }}>
              <TextArea
                rows={3}
                value={replyContent}
                onChange={(e) => setReplyContent(e.target.value)}
                placeholder="Nhập phản hồi..."
              />
              <Button
                type="primary"
                icon={<SendOutlined />}
                onClick={handleReply}
                style={{ marginTop: 8 }}
              >
                Gửi phản hồi
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default ListMessage;
