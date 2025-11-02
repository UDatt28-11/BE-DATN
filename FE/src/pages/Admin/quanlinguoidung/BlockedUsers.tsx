import React from "react";
import { Table, Card, Button, Tag, message, Modal } from "antd";
import { UnlockOutlined } from "@ant-design/icons";
import { User } from "../../../types/user/user";
interface Props {
  blockedUsers: User[];
  onUnblock: (keys: string[]) => void;
}

const BlockedUsers: React.FC<Props> = ({ blockedUsers, onUnblock }) => {
  const columns = [
    { title: "Tên", dataIndex: "name", key: "name" },
    { title: "Email", dataIndex: "email", key: "email" },
    {
      title: "Vai trò",
      dataIndex: "role",
      key: "role",
      render: (role: "admin" | "host" | "guest") => <Tag>{role}</Tag>,
    },
    {
  title: "Thao tác",
  key: "action",
  render: (_: any, record: User) => (
    <Button
      icon={<UnlockOutlined />}
      onClick={() =>
        Modal.confirm({
          title: "Mở khóa",
          content: `Mở khóa ${record.name}?`,
          onOk: () => onUnblock([record.key]),
        })
      }
    >
      Mở khóa
    </Button>
  ),
}

  ];

  return (
    <Card title="Người dùng bị khóa">
      <Table
        dataSource={blockedUsers}
        columns={columns}
        pagination={{
          pageSizeOptions: ["15", "30", "45"],
          showSizeChanger: true,
        }}
      />
    </Card>
  );
};

export default BlockedUsers;
