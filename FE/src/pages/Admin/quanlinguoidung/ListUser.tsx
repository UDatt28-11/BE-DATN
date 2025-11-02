import React, { useState, useEffect } from "react";
import {
  Table,
  Card,
  Button,
  Input,
  Space,
  Tag,
  Avatar,
  Dropdown,
  message,
  Modal,
  Spin,
} from "antd";
import {
  SearchOutlined,
  MoreOutlined,
  LockOutlined,
  UnlockOutlined,
  EditOutlined,
  DeleteOutlined,
  UserOutlined,
  PlusOutlined,
  ReloadOutlined,
} from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";
import { User } from "../../../types/user/user";
import { userService } from "../../../service/user";
import AddUser from "./AddUser";
import EditUser from "./EditUser";

const ListUser: React.FC = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchText, setSearchText] = useState("");
  const [statusFilter, setStatusFilter] = useState<string | undefined>(undefined);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [isAddVisible, setIsAddVisible] = useState(false);
  const [isEditVisible, setIsEditVisible] = useState(false);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
    total: 0,
  });

  // Load users từ API
  const loadUsers = async (page: number = 1, perPage: number = 20) => {
    setLoading(true);
    try {
      const response = await userService.getUsers({
        search: searchText || undefined,
        status: statusFilter,
        per_page: perPage,
        page: page,
        sort_by: "created_at",
        sort_order: "desc",
      });

      // Map response data về format User
      const mappedUsers: User[] = response.data.map((user) => ({
        ...user,
        key: user.id.toString(),
        name: user.full_name, // Legacy field
        phone: user.phone_number || "", // Legacy field
        avatar: user.avatar_url || "", // Legacy field
      }));

      setUsers(mappedUsers);
      setPagination({
        current: response.meta.pagination.current_page,
        pageSize: response.meta.pagination.per_page,
        total: response.meta.pagination.total,
      });
    } catch (error: any) {
      message.error("Lỗi khi tải danh sách người dùng: " + (error.response?.data?.message || error.message));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadUsers();
  }, [searchText, statusFilter]);

  const handleMenuClick = (key: string, record: User) => {
    setSelectedUser(record);
    switch (key) {
      case "edit":
        setIsEditVisible(true);
        break;
      case "block":
        Modal.confirm({
          title: "Khóa người dùng",
          content: `Bạn có chắc muốn khóa ${record.full_name}?`,
          okButtonProps: { danger: true },
          onOk: async () => {
            try {
              await userService.updateUser(record.id, { status: "locked" });
              message.success("Đã khóa người dùng");
              loadUsers();
            } catch (error: any) {
              message.error("Lỗi: " + (error.response?.data?.message || error.message));
            }
          },
        });
        break;
      case "unblock":
        Modal.confirm({
          title: "Mở khóa người dùng",
          content: `Bạn có chắc muốn mở khóa ${record.full_name}?`,
          onOk: async () => {
            try {
              await userService.updateUser(record.id, { status: "active" });
              message.success("Đã mở khóa người dùng");
              loadUsers();
            } catch (error: any) {
              message.error("Lỗi: " + (error.response?.data?.message || error.message));
            }
          },
        });
        break;
      case "delete":
        Modal.confirm({
          title: "Xóa người dùng",
          content: `Xóa ${record.full_name}? Không thể hoàn tác!`,
          okButtonProps: { danger: true },
          onOk: async () => {
            try {
              await userService.deleteUser(record.id);
              message.success("Đã xóa người dùng");
              loadUsers();
            } catch (error: any) {
              message.error("Lỗi: " + (error.response?.data?.message || error.message));
            }
          },
        });
        break;
    }
  };

  const columns: ColumnsType<User> = [
    {
      title: "Người dùng",
      dataIndex: "full_name",
      key: "full_name",
      render: (_, record) => (
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
      render: (phone) => phone || "-",
    },
    {
      title: "Giới tính",
      dataIndex: "gender",
      key: "gender",
      render: (gender) => {
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
      render: (status) => (
        <Tag color={status === "active" ? "green" : "red"}>
          {status === "active" ? "Hoạt động" : "Đã khóa"}
        </Tag>
      ),
      filters: [
        { text: "Hoạt động", value: "active" },
        { text: "Đã khóa", value: "locked" },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: "Ngày tạo",
      dataIndex: "created_at",
      key: "created_at",
      render: (date) => date ? new Date(date).toLocaleDateString("vi-VN") : "-",
    },
    {
      title: "Thao tác",
      key: "action",
      render: (_, record) => (
        <Dropdown
          menu={{
            items: [
              { key: "edit", label: "Chỉnh sửa", icon: <EditOutlined /> },
              {
                key: record.status === "active" ? "block" : "unblock",
                label: record.status === "active" ? "Khóa" : "Mở khóa",
                icon: record.status === "active" ? <LockOutlined /> : <UnlockOutlined />,
              },
              {
                key: "delete",
                label: "Xóa",
                icon: <DeleteOutlined />,
                danger: true,
              },
            ],
            onClick: ({ key }) => handleMenuClick(key, record),
          }}
          trigger={["click"]}
        >
          <Button icon={<MoreOutlined />} />
        </Dropdown>
      ),
    },
  ];

  const handleSearch = (value: string) => {
    setSearchText(value);
  };

  const handleTableChange = (pagination: any) => {
    loadUsers(pagination.current, pagination.pageSize);
  };

  return (
    <Card
      title="Danh sách người dùng"
      extra={
        <Space>
          <Input.Search
            placeholder="Tìm kiếm theo tên, email, số điện thoại..."
            allowClear
            onSearch={handleSearch}
            style={{ width: 300 }}
            prefix={<SearchOutlined />}
          />
          <Button icon={<ReloadOutlined />} onClick={() => loadUsers()}>
            Làm mới
          </Button>
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setIsAddVisible(true)}>
            Thêm người dùng
          </Button>
        </Space>
      }
    >
      <Spin spinning={loading}>
        <Table
          columns={columns}
          dataSource={users}
          pagination={{
            ...pagination,
            showSizeChanger: true,
            showTotal: (total) => `Tổng: ${total} người dùng`,
          }}
          onChange={handleTableChange}
        />
      </Spin>

      <AddUser
        visible={isAddVisible}
        onClose={() => setIsAddVisible(false)}
        onSuccess={() => {
          setIsAddVisible(false);
          loadUsers();
        }}
      />

      {selectedUser && (
        <EditUser
          visible={isEditVisible}
          user={selectedUser}
          onClose={() => {
            setIsEditVisible(false);
            setSelectedUser(null);
          }}
          onSuccess={() => {
            setIsEditVisible(false);
            setSelectedUser(null);
            loadUsers();
          }}
        />
      )}
    </Card>
  );
};

export default ListUser;
