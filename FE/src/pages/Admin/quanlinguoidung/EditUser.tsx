import React, { useEffect, useState } from "react";
import { Modal, Form, Input, Select, DatePicker, message } from "antd";
import dayjs from "dayjs";
import { User } from "../../../types/user/user";
import { userService } from "../../../service/user";

interface Props {
  visible: boolean;
  user: User;
  onClose: () => void;
  onSuccess: () => void;
}

const EditUser: React.FC<Props> = ({ visible, user, onClose, onSuccess }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (visible && user) {
      form.setFieldsValue({
        full_name: user.full_name,
        email: user.email,
        phone_number: user.phone_number,
        date_of_birth: user.date_of_birth ? dayjs(user.date_of_birth) : null,
        gender: user.gender,
        address: user.address,
        status: user.status,
      });
    }
  }, [visible, user, form]);

  const handleOk = async () => {
    try {
      const values = await form.validateFields();
      
      const userData: any = {
        full_name: values.full_name,
        email: values.email,
        phone_number: values.phone_number || undefined,
        date_of_birth: values.date_of_birth ? values.date_of_birth.format("YYYY-MM-DD") : undefined,
        gender: values.gender || undefined,
        address: values.address || undefined,
        status: values.status,
      };

      // Chỉ thêm password nếu có thay đổi
      if (values.password) {
        userData.password = values.password;
      }

      setLoading(true);
      await userService.updateUser(user.id, userData);
      message.success("Cập nhật người dùng thành công!");
      form.resetFields();
      onSuccess();
    } catch (error: any) {
      if (error.errorFields) {
        // Validation errors
        return;
      }
      message.error("Lỗi: " + (error.response?.data?.message || error.message));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title="Chỉnh sửa người dùng"
      open={visible}
      onOk={handleOk}
      onCancel={onClose}
      confirmLoading={loading}
      width={600}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="full_name"
          label="Họ và tên"
          rules={[{ required: true, message: "Vui lòng nhập họ và tên" }]}
        >
          <Input placeholder="Nhập họ và tên" />
        </Form.Item>

        <Form.Item
          name="email"
          label="Email"
          rules={[
            { required: true, message: "Vui lòng nhập email" },
            { type: "email", message: "Email không hợp lệ" },
          ]}
        >
          <Input placeholder="example@email.com" />
        </Form.Item>

        <Form.Item
          name="password"
          label="Mật khẩu mới"
          rules={[
            { min: 8, message: "Mật khẩu tối thiểu 8 ký tự" },
          ]}
        >
          <Input.Password placeholder="Để trống nếu không đổi mật khẩu" />
        </Form.Item>

        <Form.Item name="phone_number" label="Số điện thoại">
          <Input placeholder="0901234567" />
        </Form.Item>

        <Form.Item name="date_of_birth" label="Ngày sinh">
          <DatePicker
            style={{ width: "100%" }}
            format="DD/MM/YYYY"
            placeholder="Chọn ngày sinh"
          />
        </Form.Item>

        <Form.Item name="gender" label="Giới tính">
          <Select placeholder="Chọn giới tính">
            <Select.Option value="male">Nam</Select.Option>
            <Select.Option value="female">Nữ</Select.Option>
            <Select.Option value="other">Khác</Select.Option>
          </Select>
        </Form.Item>

        <Form.Item name="address" label="Địa chỉ">
          <Input.TextArea rows={2} placeholder="Nhập địa chỉ" />
        </Form.Item>

        <Form.Item
          name="status"
          label="Trạng thái"
          rules={[{ required: true }]}
        >
          <Select>
            <Select.Option value="active">Hoạt động</Select.Option>
            <Select.Option value="locked">Đã khóa</Select.Option>
          </Select>
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default EditUser;
