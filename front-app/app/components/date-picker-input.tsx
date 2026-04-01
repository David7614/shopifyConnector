import {
  BlockStack,
  Box,
  Card,
  DatePicker,
  Icon,
  Popover,
  TextField,
} from "@shopify/polaris";

import { CalendarIcon } from "@shopify/polaris-icons";
import { useEffect, useRef, useState } from "react";

interface IProps {
	name: string;
  value: string;
  onChange: any;
}

export default function DatePickerInput({ name, value, onChange }: IProps) {
  const [visible, setVisible] = useState(false);
  const [selectedDate, setSelectedDate] = useState(new Date());

  const [{ month, year }, setDate] = useState({
    month: selectedDate.getMonth(),
    year: selectedDate.getFullYear(),
  });

  const datePickerRef = useRef(null);

  function handleOnClose({ relatedTarget }: any) {
    setVisible(false);
  }

  function handleMonthChange(month: any, year: any) {
    setDate({ month, year });
  }

  function handleDateSelection({ end: newSelectedDate }: any) {
    setSelectedDate(newSelectedDate);
    setVisible(false);
    onChange(newSelectedDate.toISOString().slice(0, 10))
  }

  useEffect(() => {
    if (selectedDate) {
      setDate({
        month: selectedDate.getMonth(),
        year: selectedDate.getFullYear(),
      });
    }
  }, [selectedDate]);

  useEffect(() => {
    setSelectedDate(new Date(value))
    setDate({
      month: new Date(value).getMonth(),
      year: new Date(value).getFullYear(),
    });
  }, [value])

  return (
    <BlockStack inlineAlign="center" gap="400">
      <Box width="100%">
        <Popover
          active={visible}
          autofocusTarget="none"
          preferredAlignment="left"
          preferInputActivator={false}
          preferredPosition="below"
          preventCloseOnChildOverlayClick
          onClose={handleOnClose}
          activator={
            <TextField
							name={name}
              role="combobox"
              label={"Generate order data from"}
              prefix={<Icon source={CalendarIcon} />}
              value={value}
              onFocus={() => setVisible(true)}
              autoComplete="off"
            />
          }
        >
          <div className="w-[260px]">
            <Card ref={datePickerRef}>
              <DatePicker
                month={month}
                year={year}
                selected={selectedDate}
                onMonthChange={handleMonthChange}
                onChange={handleDateSelection}
              />
            </Card>
          </div>
        </Popover>
      </Box>
    </BlockStack>
  );
}
