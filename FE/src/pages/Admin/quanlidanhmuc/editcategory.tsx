import React, { useState, useEffect } from "react";
import { Modal, Form, Input, Upload, Checkbox, Row, Col, Space, Switch } from "antd";
import { PictureOutlined } from "@ant-design/icons";
import type { UploadFile } from "antd/es/upload/interface";
import { Amenity, Category } from "../../../types/category/category";

interface EditCategoryProps {
  visible: boolean;
  category: Category | null;
  onCancel: () => void;
  onUpdate: (
    values: any,
    fileList: UploadFile[],
    selectedAmenities: number[]
  ) => void;
  amenities: Amenity[];
}

const EditCategory: React.FC<EditCategoryProps> = ({
  visible,
  category,
  onCancel,
  onUpdate,
  amenities,
}) => {
  const [form] = Form.useForm();
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [selectedAmenities, setSelectedAmenities] = useState<number[]>([]);
  const [status, setStatus] = useState<"active" | "inactive">("active");

  useEffect(() => {
    if (category) {
      form.setFieldsValue(category);
      setFileList([
        {
          uid: "-1",
          name: "image.png",
          status: "done",
          url: category.image,
        },
      ]);
      setSelectedAmenities([1, 2, 3]); // mock tiện ích có sẵn
      setStatus(category.status as "active" | "inactive");
    }
  }, [category]);

  const handleOk = () => {
    form.validateFields().then((values) => {
      const updatedValues = {
        ...values,
        status, // thêm trạng thái vào dữ liệu gửi ra ngoài
      };
      onUpdate(updatedValues, fileList, selectedAmenities);
    });
  };

  return (
    <Modal
      title="Chỉnh sửa danh mục"
      open={visible}
      onOk={handleOk}
      onCancel={() => {
        onCancel();
        form.resetFields();
      }}
      okText="Cập nhật"
      width={700}
    >
      <Form form={form} layout="vertical">
        {/* --- Tên danh mục --- */}
        <Form.Item
          name="name"
          label="Tên danh mục"
          rules={[{ required: true, message: "Vui lòng nhập tên danh mục!" }]}
        >
          <Input placeholder="VD: Nhà gỗ, Villa, Căn hộ..." size="large" />
        </Form.Item>

        {/* --- Mô tả --- */}
        <Form.Item
          name="description"
          label="Mô tả"
          rules={[{ required: true, message: "Vui lòng nhập mô tả!" }]}
        >
          <Input.TextArea rows={4} placeholder="Nhập mô tả chi tiết..." />
        </Form.Item>

        {/* --- Hình ảnh --- */}
        <Form.Item label="Hình ảnh">
          <Upload
            listType="picture-card"
            fileList={fileList}
            onChange={({ fileList }) => setFileList(fileList)}
            maxCount={1}
            beforeUpload={() => false}
          >
            {fileList.length === 0 && (
              <div>
                <PictureOutlined />
                <div style={{ marginTop: 8 }}>Upload</div>
              </div>
            )}
          </Upload>
        </Form.Item>

        {/* --- Trạng thái --- */}
        <Form.Item label="Trạng thái">
          <Space>
            <Switch
              checked={status === "active"}
              onChange={(checked) => setStatus(checked ? "active" : "inactive")}
              checkedChildren="Kích hoạt"
              unCheckedChildren="Khóa"
            />
            <span>{status === "active" ? "Đang kích hoạt" : "Đang khóa"}</span>
          </Space>
        </Form.Item>

        {/* --- Tiện ích liên quan --- */}
        <Form.Item label="Tiện ích liên quan">
          <Checkbox.Group
            value={selectedAmenities}
            onChange={(values) => setSelectedAmenities(values as number[])}
            style={{ width: "100%" }}
          >
            <Row gutter={[16, 16]}>
              {amenities.map((amenity) => (
                <Col span={12} key={amenity.id}>
                  <Checkbox value={amenity.id}>
                    <Space>
                      <span style={{ fontSize: 18 }}>{amenity.icon}</span>
                      {amenity.name}
                    </Space>
                  </Checkbox>
                </Col>
              ))}
            </Row>
          </Checkbox.Group>
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default EditCategory;
