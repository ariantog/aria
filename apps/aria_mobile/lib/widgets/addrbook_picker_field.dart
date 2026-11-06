import 'package:flutter/material.dart';

import '../api/aria_api.dart';

/// Search + pick an addrbook row (customers table) for mobile party fields.
class AddrbookPickerField extends StatefulWidget {
  const AddrbookPickerField({
    super.key,
    required this.api,
    required this.lookupType,
    this.addrbookType,
    required this.label,
    required this.onChanged,
    this.selectedId,
    this.selectedName,
  });

  final AriaApi api;
  final String lookupType;
  final String? addrbookType;
  final String label;
  final void Function(int id, String name) onChanged;
  final int? selectedId;
  final String? selectedName;

  @override
  State<AddrbookPickerField> createState() => _AddrbookPickerFieldState();
}

class _AddrbookPickerFieldState extends State<AddrbookPickerField> {
  Future<void> _open() async {
    final search = TextEditingController();
    List<dynamic> results = [];

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(
            left: 16,
            right: 16,
            top: 16,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
          ),
          child: StatefulBuilder(
            builder: (context, setModal) {
              return Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Select ${widget.label}', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  TextField(
                    controller: search,
                    decoration: const InputDecoration(
                      labelText: 'Search (min 3 characters)',
                      border: OutlineInputBorder(),
                    ),
                    onChanged: (v) async {
                      if (v.trim().length < 3) {
                        setModal(() => results = []);
                        return;
                      }
                      try {
                        final rows = await widget.api.searchAddrbook(
                          widget.lookupType,
                          v.trim(),
                          addrbookType: widget.addrbookType,
                        );
                        setModal(() => results = rows);
                      } catch (e) {
                        setModal(() => results = []);
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 240,
                    child: ListView.builder(
                      itemCount: results.length,
                      itemBuilder: (c, i) {
                        final row = results[i] as Map<String, dynamic>;
                        final id = row['id'];
                        final name = row['name']?.toString() ?? '';
                        return ListTile(
                          title: Text(name),
                          subtitle: Text('ID: $id'),
                          onTap: () {
                            widget.onChanged(int.parse(id.toString()), name);
                            Navigator.pop(context);
                          },
                        );
                      },
                    ),
                  ),
                ],
              );
            },
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final hint = widget.selectedId != null ? '${widget.selectedName ?? ""} (#${widget.selectedId})' : 'Tap to search';

    return ListTile(
      title: Text(widget.label),
      subtitle: Text(hint),
      trailing: const Icon(Icons.search),
      onTap: _open,
    );
  }
}
