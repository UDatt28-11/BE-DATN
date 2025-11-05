import React, { useEffect, useState } from "react";
import {
  Table,
  Button,
  Space,
  Tag,
  Avatar,
  Modal,
  Input,
  Tooltip,
  message,
  Spin,
  notification,
} from "antd";
import {
  PlusOutlined,
  EditOutlined,
  DeleteOutlined,
  LockOutlined,
  UnlockOutlined,
  ReloadOutlined,
  UserOutlined,
} from "@ant-design/icons";

import { User } from "../../../types/user/user";
import { userService } from "../../../service/user";
import AddUser from "./AddUser";
import EditUser from "./EditUser";

const { Search } = Input;

const ListUser: React.FC = () => {
  const [data, setData] = useState<User[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [addModal, setAddModal] = useState(false);
  const [editModal, setEditModal] = useState(false);
  const [selected, setSelected] = useState<User | null>(null);

  /** 🔹 Gọi API lấy danh sách người dùng */
  const fetchUsers = async () => {
    setLoading(true);
    try {
      const res = await userService.getUsers({
        per_page: 100,
        page: 1,
        sort_by: "created_at",
        sort_order: "desc",
      });

      if (Array.isArray(res.data)) {
        // Map UserResponse[] sang User[] với role casting
        const mappedData: User[] = res.data.map((user) => ({
          ...user,
          role: user.role as any, // Cast role từ string sang UserRole
        }));
        setData(mappedData);
      } else {
        setData([]);
        message.warning("API không trả về danh sách hợp lệ!");
      }
    } catch (err: any) {
      console.error(err);
      message.error("Không thể tải danh sách người dùng!");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  /** 🔹 Tìm kiếm */
  const filteredData = data.filter(
    (item) =>
      item.full_name.toLowerCase().includes(search.toLowerCase()) ||
      item.email.toLowerCase().includes(search.toLowerCase()) ||
      item.phone_number?.toLowerCase().includes(search.toLowerCase())
  );

  /** 🔹 Khóa người dùng */
  const handleLock = (user: User) => {
    Modal.confirm({
      title: "Khóa người dùng",
      content: `Bạn có chắc chắn muốn khóa "${user.full_name}"?`,
      okText: "Khóa",
      okType: "danger",
      cancelText: "Hủy",
      centered: true,
      async onOk() {
        try {
          await userService.updateUser(user.id, { status: "locked" });
          setData((prev) =>
            prev.map((d) => (d.id === user.id ? { ...d, status: "locked" as "active" | "locked" } : d))
          );
          notification.success({
            message: "Khóa thành công!",
            description: `Người dùng "${user.full_name}" đã bị khóa.`,
            placement: "topRight",
            duration: 3,
          });
          fetchUsers();
        } catch (err: any) {
          notification.error({
            message: "Khóa thất bại!",
            description:
              err.response?.data?.message ||
              err.message ||
              "Không thể khóa người dùng.",
            placement: "topRight",
            duration: 5,
          });
        }
      },
    });
  };

  /** 🔹 Mở khóa người dùng */
  const handleUnlock = (user: User) => {
    Modal.confirm({
      title: "Mở khóa người dùng",
      content: `Bạn có chắc chắn muốn mở khóa "${user.full_name}"?`,
      okText: "Mở khóa",
      cancelText: "Hủy",
      centered: true,
      async onOk() {
        try {
          await userService.updateUser(user.id, { status: "active" });
          setData((prev) =>
            prev.map((d) => (d.id === user.id ? { ...d, status: "active" as "active" | "locked" } : d))
          );
          notification.success({
            message: "Mở khóa thành công!",
            description: `Người dùng "${user.full_name}" đã được mở khóa.`,
            placement: "topRight",
            duration: 3,
          });
          fetchUsers();
        } catch (err: any) {
          notification.error({
            message: "Mở khóa thất bại!",
            description:
              err.response?.data?.message ||
              err.message ||
              "Không thể mở khóa người dùng.",
            placement: "topRight",
            duration: 5,
          });
        }
      },
    });
  };

  /** 🔹 Xóa người dùng */
  const handleDelete = (user: User) => {
    Modal.confirm({
      title: "Xác nhận xóa người dùng",
      content: `Bạn có chắc chắn muốn xóa "${user.full_name}"? Không thể hoàn tác!`,
      okText: "Xóa",
      okType: "danger",
      cancelText: "Hủy",
      centered: true,
      async onOk() {
        try {
          await userService.deleteUser(user.id);
          setData((prev) => prev.filter((d) => d.id !== user.id));
          notification.success({
            message: "Xóa thành công!",
            description: `Người dùng "${user.full_name}" đã được xóa khỏi hệ thống.`,
            placement: "topRight",
            duration: 3,
          });
          fetchUsers();
        } catch (err: any) {
          notification.error({
            message: "Xóa thất bại!",
            description:
              err.response?.data?.message ||
              err.message ||
              "Không thể xóa người dùng.",
            placement: "topRight",
            duration: 5,
          });
        }
      },
    });
  };

  const columns = [
    {
      title: "Người dùng",
      dataIndex: "full_name",
      key: "full_name",
      render: (_: any, record: any) => (
        <Space>
          <Avatar src={record.avatar_url} icon={<UserOutlined />} />
          <div>
            <div>{record.full_name}</div>
            <div style={{ fontSize: 12, color: "#888" }}>ID: {record.id}</div>
          </div>
        </Space>
      ),
    },
    {
      title: "Email",
      dataIndex: "email",
      key: "email",
    },
    {
      title: "Số điện thoại",
      dataIndex: "phone_number",
      key: "phone_number",
      render: (phone: any) => phone || "-",
    },
    {
      title: "Giới tính",
      dataIndex: "gender",
      key: "gender",
      render: (gender: any) => {
        const labels: Record<string, string> = {
          male: "Nam",
          female: "Nữ",
          other: "Khác",
        };
        return gender ? labels[gender] || gender : "-";
      },
    },
    {
      title: "Trạng thái",
      dataIndex: "status",
      key: "status",
      render: (status: any) => (
        <Tag color={status === "active" ? "green" : "red"}>
          {status === "active" ? "Hoạt động" : "Đã khóa"}
        </Tag>
      ),
    },
    {
      title: "Role",
      dataIndex: "role",
      key: "role",
      render: (role: any) => {
        const r = String(role || "").toLowerCase();
        const color =
          r === "admin" ? "green" : r === "staff" || r === "host" ? "orange" : "blue";
        const label =
          r === "admin"
            ? "Quản trị"
            : r === "staff" || r === "host"
            ? "Nhân viên"
            : "Người dùng";
        return <Tag color={color}>{label}</Tag>;
      },
    },
    {
      title: "Ngày tạo",
      dataIndex: "created_at",
      key: "created_at",
      render: (date: any) =>
        date ? new Date(date).toLocaleDateString("vi-VN") : "-",
    },
    {
      title: "Thao tác",
      key: "actions",
      width: 180,
      render: (_: any, record: any) => (
        <Space>
          <Tooltip title="Chỉnh sửa">
            <Button
              icon={<EditOutlined />}
              onClick={() => {
                setSelected(record);
                setEditModal(true);
              }}
            />
          </Tooltip>
          {record.status === "active" ? (
            <Tooltip title="Khóa">
              <Button
                icon={<LockOutlined />}
                danger
                onClick={() => handleLock(record)}
              />
            </Tooltip>
          ) : (
            <Tooltip title="Mở khóa">
              <Button
                icon={<UnlockOutlined />}
                onClick={() => handleUnlock(record)}
              />
            </Tooltip>
          )}
          <Tooltip title="Xóa">
            <Button
              icon={<DeleteOutlined />}
              danger
              onClick={() => handleDelete(record)}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Search
          placeholder="Tìm kiếm theo tên, email, số điện thoại..."
          allowClear
          onSearch={setSearch}
          style={{ width: 350 }}
        />
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => setAddModal(true)}
        >
          Thêm người dùng
        </Button>
        <Button
          icon={<ReloadOutlined />}
          onClick={fetchUsers}
          loading={loading}
        >
          Làm mới
        </Button>
      </Space>

      <Spin spinning={loading}>
        <Table
          rowKey="id"
          columns={columns}
          dataSource={filteredData}
          pagination={{ pageSize: 10, showSizeChanger: true }}
          bordered
        />
      </Spin>

      {/* Modal thêm người dùng */}
      <AddUser
        visible={addModal}
        onClose={() => setAddModal(false)}
        onSuccess={() => {
          setAddModal(false);
          fetchUsers();
        }}
      />

      {/* Modal sửa người dùng */}
      {selected && (
        <EditUser
          visible={editModal}
          user={selected}
          onClose={() => {
            setEditModal(false);
            setSelected(null);
          }}
          onSuccess={() => {
            setEditModal(false);
            setSelected(null);
            fetchUsers();
          }}
        />
      )}
    </div>
  );
};

export default ListUser;
